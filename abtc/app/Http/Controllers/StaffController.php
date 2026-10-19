<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Services\Audit;
use App\Services\QueueTicketNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    private const ACTIVE_TICKET_STATUSES = ['waiting', 'called', 'in_progress', 'held'];

    public function dashboard(): View
    {
        return view('staff.dashboard', [
            'patientsToday' => Patient::whereDate('created_at', today())->count(),
            'waitingCount' => Ticket::where('status', 'waiting')->count(),
            'servingCount' => Ticket::whereIn('status', ['called', 'in_progress'])->count(),
            'recentTickets' => Ticket::with(['patient', 'vaccinationSchedule'])
                ->whereIn('status', self::ACTIVE_TICKET_STATUSES)
                ->latest()->take(8)->get(),
        ]);
    }

    public function searchPatients(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['search'] ?? '');
        $patients = Patient::with([
            'latestTicket',
            'vaccinationSchedules' => fn ($query) => $query->whereIn('status', ['scheduled', 'missed'])->orderBy('scheduled_date'),
        ])
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('middle_name', 'like', "%{$term}%")
                        ->orWhere('contact_number', 'like', "%{$term}%")
                        ->orWhereHas('tickets', fn (Builder $ticketQuery) => $ticketQuery->where('ticket_number', 'like', "%{$term}%"));
                });
            })
            ->orderBy('last_name')->orderBy('first_name')->paginate(15)->withQueryString();

        return view('staff.patients.search', compact('patients', 'term'));
    }

    public function createTicket(): View
    {
        $dueSchedules = $this->eligibleSchedules();
        return view('staff.tickets.create', compact('dueSchedules'));
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $data = $request->validate(['schedule_id' => ['required', 'integer', 'exists:vaccination_schedules,id']]);

        $ticket = DB::transaction(function () use ($data) {
            $schedule = VaccinationSchedule::with('patient')->lockForUpdate()->findOrFail($data['schedule_id']);
            if (! in_array($schedule->status, ['scheduled', 'missed'], true)) {
                throw ValidationException::withMessages(['schedule_id' => 'Only a scheduled or missed dose can be queued.']);
            }
            if ($schedule->scheduled_date->gt(today())) {
                throw ValidationException::withMessages(['schedule_id' => 'This dose is not due yet.']);
            }
            if (VaccinationSchedule::where('patient_id', $schedule->patient_id)
                ->where('dose_number', '<', $schedule->dose_number)
                ->where('status', '!=', 'administered')->exists()) {
                throw ValidationException::withMessages(['schedule_id' => 'Complete earlier doses before queueing this dose.']);
            }
            if (Ticket::where('patient_id', $schedule->patient_id)
                ->whereIn('status', self::ACTIVE_TICKET_STATUSES)->exists()) {
                throw ValidationException::withMessages(['schedule_id' => 'This patient already has an active queue ticket.']);
            }

            return QueueTicketNumber::forReturningPatient($schedule->patient, $schedule);
        });

        Audit::log('queue.staff_ticket', "Staff queued ticket {$ticket->ticket_number} for patient #{$ticket->patient_id}", $ticket);
        return redirect()->route('staff.tickets.print', $ticket)->with('status', 'Returning-patient ticket created.');
    }

    public function queue(): View
    {
        $waiting = Ticket::with(['patient', 'vaccinationSchedule'])
            ->where('status', 'waiting')->priorityFirst()->get();
        return view('staff.queue', compact('waiting'));
    }

    public function printTicket(Ticket $ticket): View
    {
        $ticket->load(['patient', 'vaccinationSchedule']);
        return view('staff.tickets.print', compact('ticket'));
    }

    private function eligibleSchedules()
    {
        return VaccinationSchedule::with(['patient', 'tickets'])
            ->whereIn('status', ['scheduled', 'missed'])
            ->whereDate('scheduled_date', '<=', today())
            ->orderBy('scheduled_date')->get()
            ->filter(function (VaccinationSchedule $schedule) {
                $hasEarlierDose = VaccinationSchedule::where('patient_id', $schedule->patient_id)
                    ->where('dose_number', '<', $schedule->dose_number)
                    ->where('status', '!=', 'administered')->exists();
                $hasActiveTicket = Ticket::where('patient_id', $schedule->patient_id)
                    ->whereIn('status', self::ACTIVE_TICKET_STATUSES)->exists();

                return ! $hasEarlierDose && ! $hasActiveTicket;
            })->values();
    }
}
