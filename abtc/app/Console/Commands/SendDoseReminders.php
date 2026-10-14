<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\SmsLog;
use App\Models\SystemSetting;
use App\Models\VaccinationSchedule;
use App\Services\SmsGateway;
use Illuminate\Console\Command;

class SendDoseReminders extends Command
{
    protected $signature = 'sms:send-reminders';
    protected $description = 'Send SMS reminders to patients with a dose due within the configured lead time.';

    public function handle(SmsGateway $client): int
    {
        $leadHours = (int) SystemSetting::get('sms_reminder_lead_hours', env('SMS_REMINDER_LEAD_HOURS', 24));

        if ($leadHours <= 0) {
            $this->info('Auto reminders disabled (lead time = 0).');
            return self::SUCCESS;
        }

        $from = now()->addHours($leadHours - 1);
        $to = now()->addHours($leadHours + 1);

        $due = VaccinationSchedule::with('patient')
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $template = SystemSetting::get('sms_template',
            "Dear {patient_name}, this is a reminder for your anti-rabies vaccination (Dose {dose_number}) scheduled on {date} at {time}. Please bring this SMS and your vaccination card.");

        $sent = 0;
        foreach ($due as $schedule) {
            $patient = $schedule->patient;
            if (! $patient || ! $patient->contact_number) {
                continue;
            }
            if (SmsLog::where('patient_id', $patient->id)
                ->where('message', 'like', "%Dose {$schedule->dose_number}%")
                ->whereDate('created_at', now()->toDateString())
                ->exists()) {
                continue; // already reminded today
            }

            $message = str_replace(
                ['{patient_name}', '{dose_number}', '{date}', '{time}'],
                [$patient->first_name, $schedule->dose_number, $schedule->scheduled_date->format('F d, Y'), '8:00 AM'],
                $template
            );

            $result = $client->send($patient->contact_number, $message);
            SmsLog::create([
                'patient_id' => $patient->id,
                'user_id' => null,
                'message' => $message,
                'recipient' => $patient->contact_number,
                'status' => $result['status'],
                'response' => is_array($result['response']) ? json_encode($result['response']) : (string) $result['response'],
                'sent_at' => $result['status'] === 'delivered' ? now() : null,
            ]);
            $sent++;
        }

        $this->info("Submitted {$sent} reminder SMS to the gateway.");
        return self::SUCCESS;
    }
}
