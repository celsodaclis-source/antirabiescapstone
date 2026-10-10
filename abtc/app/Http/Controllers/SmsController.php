<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use App\Models\VaccinationSchedule;
use App\Services\Audit;
use App\Services\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsController extends Controller
{
    public const DEFAULT_TEMPLATE = "Dear {patient_name}, this is a reminder for your anti-rabies vaccination (Dose {dose_number}) scheduled on {date} at {time}. Please bring this SMS and your vaccination card.";

    protected SmsGateway $client;

    public function __construct(SmsGateway $client)
    {
        $this->client = $client;
    }

    public function index(): View
    {
        $logs = SmsLog::with(['patient', 'user'])->latest()->paginate(20);
        $leadHours = (int) (SystemSetting::get('sms_reminder_lead_hours', env('SMS_REMINDER_LEAD_HOURS', 24)));

        $upcoming = VaccinationSchedule::with('patient')
            ->where('status', 'scheduled')
            ->where('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->take(15)
            ->get();

        return view('sms.index', compact('logs', 'leadHours', 'upcoming'));
    }

    public function template(): View
    {
        $template = SystemSetting::get('sms_template', self::DEFAULT_TEMPLATE);
        $leadHours = (int) SystemSetting::get('sms_reminder_lead_hours', env('SMS_REMINDER_LEAD_HOURS', 24));
        return view('sms.template', compact('template', 'leadHours'));
    }

    public function updateTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template' => ['required', 'string'],
            'lead_hours' => ['required', 'integer', 'min:0'],
        ]);

        SystemSetting::set('sms_template', $data['template']);
        SystemSetting::set('sms_reminder_lead_hours', $data['lead_hours']);

        Audit::log('sms.template', 'Updated SMS reminder template and lead time');

        return redirect()->route('sms.index')->with('status', 'SMS template saved.');
    }

    public function send(Request $request, VaccinationSchedule $schedule): RedirectResponse
    {
        $schedule->load('patient');

        if (! $schedule->patient || ! $schedule->patient->contact_number) {
            $this->logSkipped($schedule, 'Patient has no contact number on record.');
            return back()->with('status', 'Patient has no contact number on record.');
        }

        $message = $this->buildMessage($schedule, SystemSetting::get('sms_template', self::DEFAULT_TEMPLATE));
        $log = $this->dispatch($schedule->patient, $message);

        return back()->with('status', "SMS to {$schedule->patient->fullName()}: {$log->status}.");
    }

    public function sendToPatient(Patient $patient): RedirectResponse
    {
        if (! $patient->contact_number) {
            $this->logSkipped(null, "Patient {$patient->fullName()} has no contact number on record.");
            return back()->with('status', 'Patient has no contact number on record.');
        }

        $next = $patient->vaccinationSchedules()
            ->where('status', 'scheduled')
            ->orderBy('scheduled_date')
            ->first();

        if (! $next) {
            $this->logSkipped($patient, 'No upcoming scheduled dose for this patient.');
            return back()->with('status', 'No upcoming scheduled dose for this patient.');
        }

        $message = $this->buildMessage($next, SystemSetting::get('sms_template', self::DEFAULT_TEMPLATE));
        $log = $this->dispatch($patient, $message);

        return back()->with('status', "SMS to {$patient->fullName()}: {$log->status}.");
    }

    public function sendOverdue(): RedirectResponse
    {
        $overdue = VaccinationSchedule::with('patient')
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($overdue as $schedule) {
            if ($schedule->patient && $schedule->patient->contact_number) {
                $message = $this->buildMessage($schedule, SystemSetting::get('sms_template', self::DEFAULT_TEMPLATE));
                $this->dispatch($schedule->patient, $message);
                $count++;
            }
        }

        return back()->with('status', "Sent {$count} reminder SMS to overdue patients.");
    }

    public function testConnection(): RedirectResponse
    {
        $status = $this->client->testConnection();
        return back()->with('gateway_status', $status);
    }

    public function settings(): View
    {
        $settings = SmsGateway::getSettings();
        return view('sms.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'host' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'device_id' => ['nullable', 'string'],
        ]);

        SmsGateway::saveSettings($data);
        Audit::log('sms.gateway_settings', 'Updated SMS Gateway settings');

        return redirect()->route('sms.settings')->with('status', 'SMS Gateway settings saved.');
    }

    /**
     * Log a skipped SMS attempt so it appears in the SMS log.
     */
    protected function logSkipped($patientOrSchedule, string $reason): void
    {
        $patientId = null;
        $recipient = 'N/A';

        if ($patientOrSchedule instanceof Patient) {
            $patientId = $patientOrSchedule->id;
            $recipient = $patientOrSchedule->contact_number ?: 'N/A';
        } elseif ($patientOrSchedule instanceof VaccinationSchedule && $patientOrSchedule->patient) {
            $patientId = $patientOrSchedule->patient->id;
            $recipient = $patientOrSchedule->patient->contact_number ?: 'N/A';
        }

        SmsLog::create([
            'patient_id' => $patientId,
            'user_id' => auth()->id(),
            'message' => $reason,
            'recipient' => $recipient,
            'status' => 'failed',
            'response' => 'Skipped: '.$reason,
            'sent_at' => null,
        ]);
    }

    protected function buildMessage(VaccinationSchedule $schedule, string $template): string
    {
        $patient = $schedule->patient;
        return str_replace(
            ['{patient_name}', '{dose_number}', '{date}', '{time}'],
            [
                $patient ? $patient->first_name : 'Patient',
                $schedule->dose_number,
                $schedule->scheduled_date->format('F d, Y'),
                '8:00 AM',
            ],
            $template
        );
    }

    protected function dispatch(Patient $patient, string $message): SmsLog
    {
        $result = $this->client->send($patient->contact_number, $message);

        $log = SmsLog::create([
            'patient_id' => $patient->id,
            'user_id' => auth()->id(),
            'message' => $message,
            'recipient' => $patient->contact_number,
            'status' => $result['status'],
            'response' => is_array($result['response']) ? json_encode($result['response']) : (string) $result['response'],
            'sent_at' => $result['status'] === 'delivered' ? now() : null,
        ]);

        Audit::log('sms.send', "SMS to {$patient->fullName()} -> {$log->status}", $log);

        return $log;
    }
}
