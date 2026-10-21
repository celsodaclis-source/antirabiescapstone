<?php

namespace App\Http\Controllers;

use App\Models\DoctorStation;
use App\Models\NurseStation;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class QueueMonitoringController extends Controller
{
    public function index(): View
    {
        $data = $this->snapshot();

        return view('queue.monitoring', [
            'stats' => $data['stats'],
            'nurseStations' => collect($data['nurse_stations']),
            'doctorStations' => collect($data['doctor_stations']),
            'nowServing' => $data['now_serving'],
            'nurseWaiting' => $data['nurse_waiting'],
            'doctorWaiting' => $data['doctor_waiting'],
            'held' => $data['held'],
            'servedToday' => $data['served_today'],
            'updated_at' => $data['updated_at'],
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->snapshot());
    }

    /** Current, read-only view of the shared ticket and station records. */
    private function snapshot(): array
    {
        $assessmentStages = ['assessment', 'medical_review'];
        $activeStatuses = ['called', 'in_progress'];

        $nurseWaitingQuery = Ticket::query()
            ->where('status', 'waiting')
            ->whereIn('service_stage', $assessmentStages)
            ->whereNull('assessment_completed_at')
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', $activeStatuses));

        $doctorWaitingQuery = Ticket::query()
            ->where('status', 'waiting')
            ->where(function ($query) use ($assessmentStages) {
                $query->where(function ($assessment) use ($assessmentStages) {
                    $assessment->whereIn('service_stage', $assessmentStages)
                        ->whereNotNull('assessment_completed_at');
                })->orWhere('service_stage', 'follow_up');
            })
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', $activeStatuses))
            ;

        $activeTickets = Ticket::query()
            ->with(['patient', 'assignedStaff:id,name,role', 'vaccinationSchedule', 'doctorStation:id,name'])
            ->whereIn('status', $activeStatuses)
            ->orderBy('called_at')
            ->get();

        $nurseWaitingTickets = (clone $nurseWaitingQuery)
            ->with(['patient', 'vaccinationSchedule'])
            ->priorityFirst()
            ->get();
        $doctorWaitingTickets = (clone $doctorWaitingQuery)
            ->with(['patient', 'vaccinationSchedule'])
            ->priorityFirst()
            ->get();
        $heldTickets = Ticket::query()
            ->with(['patient', 'vaccinationSchedule', 'assignedStaff', 'doctorStation:id,name'])
            ->where('status', 'held')
            ->orderBy('updated_at')
            ->get();
        $servedTickets = Ticket::query()
            ->with(['patient', 'assignedStaff', 'doctorStation:id,name'])
            ->where('status', 'completed')
            ->whereDate('completed_at', now()->toDateString())
            ->orderByDesc('completed_at')
            ->get();

        $nurseStations = NurseStation::query()->with('nurse:id,name,role')->orderBy('id')->get();
        $doctorStations = DoctorStation::query()->with('doctor:id,name,role')->orderBy('id')->get();

        $nurseActive = $activeTickets->filter(fn (Ticket $ticket) =>
            $ticket->assignedStaff?->role === 'Nurse'
            && in_array($ticket->service_stage, $assessmentStages, true)
        )->values();

        $doctorActive = $activeTickets->filter(fn (Ticket $ticket) =>
            $ticket->assignedStaff?->role === 'Doctor'
            && ($ticket->doctor_station_id !== null || in_array($ticket->service_stage, $assessmentStages, true) || $ticket->service_stage === 'follow_up')
        )->values();
        $nurseServing = $nurseActive->where('status', 'in_progress')->values();
        $doctorServing = $doctorActive->where('status', 'in_progress')->values();

        $nurseStations = $nurseStations->map(function (NurseStation $station) use ($nurseActive) {
            $ticket = $nurseActive->first(fn (Ticket $active) => (int) $active->counter_assigned === (int) $station->id);

            return [
                'id' => $station->id,
                'name' => $station->name,
                'assigned_nurse' => $station->nurse?->name,
                'ticket' => $ticket?->ticket_number,
                'status' => $ticket ? ($ticket->status === 'in_progress' ? 'Serving' : 'Called') : 'Idle',
            ];
        })->values();

        $doctorStations = $doctorStations->map(function (DoctorStation $station) use ($doctorActive) {
            $ticket = $doctorActive->first(fn (Ticket $active) =>
                (int) ($active->doctor_station_id ?? $active->counter_assigned) === (int) $station->id
            );

            return [
                'id' => $station->id,
                'name' => $station->name,
                'assigned_doctor' => $station->doctor?->name,
                'ticket' => $ticket?->ticket_number,
                'status' => $ticket ? ($ticket->status === 'in_progress' ? 'Serving' : 'Called') : 'Idle',
            ];
        })->values();

        return [
            'stats' => [
                'nurse_waiting' => $nurseWaitingTickets->count(),
                'doctor_waiting' => $doctorWaitingTickets->count(),
                'now_serving' => $nurseServing->count() + $doctorServing->count(),
                'on_hold' => $heldTickets->count(),
                'served_today' => $servedTickets->count(),
                'active_stations' => ($nurseServing->count() + $doctorServing->count()).'/'.($nurseStations->count() + $doctorStations->count()),
                'nurse_serving' => $nurseServing->count(),
                'doctor_serving' => $doctorServing->count(),
            ],
            'nurse_stations' => $nurseStations,
            'doctor_stations' => $doctorStations,
            'now_serving' => $nurseActive->concat($doctorActive)->unique('id')->values()->map(fn (Ticket $ticket) => $this->queueRow($ticket))->values(),
            'nurse_waiting' => $nurseWaitingTickets->map(fn (Ticket $ticket) => $this->queueRow($ticket))->values(),
            'doctor_waiting' => $doctorWaitingTickets->map(fn (Ticket $ticket) => $this->queueRow($ticket))->values(),
            'held' => $heldTickets->map(fn (Ticket $ticket) => $this->queueRow($ticket))->values(),
            'served_today' => $servedTickets->map(fn (Ticket $ticket) => $this->queueRow($ticket))->values(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function queueRow(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'patient_name' => $ticket->patient?->fullName() ?? 'Patient record unavailable',
            'patient_type' => $ticket->patient?->patientTypeLabel() ?? '',
            'is_priority' => $ticket->is_priority,
            'visit_type' => ucfirst($ticket->visit_type),
            'service_stage' => str_replace('_', ' ', ucfirst($ticket->service_stage)),
            'dose_number' => $ticket->vaccinationSchedule?->dose_number,
            'counter' => $ticket->counter_assigned,
            'assigned_staff' => $ticket->assignedStaff?->name,
            'assigned_role' => $ticket->assignedStaff?->role,
            'station_name' => $ticket->doctorStation?->name ?? ($ticket->assignedStaff?->role === 'Nurse' && $ticket->counter_assigned ? 'Nurse '.$ticket->counter_assigned : null),
            'status' => $ticket->status,
            'completed_time' => $ticket->completed_at?->format('h:i A'),
            'updated_time' => $ticket->updated_at?->format('h:i A'),
        ];
    }
}
