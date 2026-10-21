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

        $nurseWaiting = Ticket::query()
            ->where('status', 'waiting')
            ->whereIn('service_stage', $assessmentStages)
            ->whereNull('assessment_completed_at')
            ->count();

        $doctorWaiting = Ticket::query()
            ->where('status', 'waiting')
            ->where(function ($query) use ($assessmentStages) {
                $query->where(function ($assessment) use ($assessmentStages) {
                    $assessment->whereIn('service_stage', $assessmentStages)
                        ->whereNotNull('assessment_completed_at');
                })->orWhere('service_stage', 'follow_up');
            })
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', $activeStatuses))
            ->count();

        $activeTickets = Ticket::query()
            ->with(['assignedStaff:id,name,role'])
            ->whereIn('status', $activeStatuses)
            ->orderBy('called_at')
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
                'nurse_waiting' => $nurseWaiting,
                'doctor_waiting' => $doctorWaiting,
                'nurse_serving' => $nurseServing->count(),
                'doctor_serving' => $doctorServing->count(),
            ],
            'nurse_stations' => $nurseStations,
            'doctor_stations' => $doctorStations,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
