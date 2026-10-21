<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use App\Models\SmsLog;
use App\Models\NurseStation;
use App\Models\DoctorStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $role = $user->role;

        if ($role === 'Nurse') {
            return view('nurse.dashboard', [
                'stations' => NurseStation::with('nurse')->orderBy('id')->get(),
                'assignedStation' => NurseStation::where('user_id', $user->id)->first(),
            ]);
        }

        if ($role === 'Doctor') {
            $dashboard = $this->doctorDashboardData($user);

            return view('doctor.dashboard', [
                'stations' => DoctorStation::with('doctor')->orderBy('id')->get(),
                'assignedStation' => DoctorStation::where('user_id', $user->id)->first(),
                ...$dashboard,
            ]);
        }

        $today = Carbon::today();

        // ── Common KPIs ──
        $patientsToday = Patient::whereDate('created_at', $today)->count();
        $queueCount = Ticket::whereIn('status', ['waiting', 'called', 'in_progress', 'held'])->count();
        $waitingPatients = Ticket::where('status', 'waiting')->count();
        $currentlyServing = Ticket::where('status', 'in_progress')->count();
        $completedPatients = Ticket::where('status', 'completed')->whereDate('completed_at', $today)->count();
        $upcomingVaccinations = VaccinationSchedule::where('status', 'scheduled')
            ->whereDate('scheduled_date', '>=', $today)
            ->whereDate('scheduled_date', '<=', $today->copy()->addDays(7))
            ->count();
        $overdueDoseCount = VaccinationSchedule::whereIn('status', ['scheduled', 'missed'])
            ->whereDate('scheduled_date', '<', $today)->count();
        $vaccineStockAlerts = VaccineInventory::whereRaw('quantity <= low_stock_threshold')->count();
        $totalStock = VaccineInventory::sum('quantity');
        $pendingFollowups = VaccinationSchedule::where('status', 'scheduled')
            ->where('scheduled_date', '<=', $today)->count();

        // ── Overdue doses (relevant to all roles) ──
        $overdue = VaccinationSchedule::with('patient')
            ->whereIn('status', ['scheduled', 'missed'])
            ->where('scheduled_date', '<', $today)
            ->orderBy('scheduled_date')
            ->get();

        // ── Active queue (relevant to all roles) ──
        $recentTickets = Ticket::with(['patient', 'assignedStaff', 'vaccinationSchedule'])
            ->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])
            ->orderBy('created_at')
            ->take(8)
            ->get();

        // ── Role-specific data ──
        $lowStock = collect();
        $expiringSoon = collect();
        $smsRecent = collect();
        $labels = [];
        $values = [];
        $todaySchedules = collect();
        $servedTodayCount = 0;

        if ($role === 'Admin') {
            // Admin sees everything
            $lowStock = VaccineInventory::whereRaw('quantity <= low_stock_threshold')->get();
            $expiringSoon = VaccineInventory::where('expiration_date', '<=', $today->copy()->addDays(30))
                ->where('expiration_date', '>=', $today)
                ->get();

            $monthStart = $today->copy()->startOfMonth();
            $volume = Patient::where('created_at', '>=', $monthStart)
                ->get()
                ->groupBy(fn ($p) => $p->created_at->format('Y-m-d'))
                ->map(fn ($g) => $g->count());

            for ($d = $monthStart->copy(); $d->lte($today); $d->addDay()) {
                $labels[] = $d->format('M d');
                $values[] = $volume->get($d->format('Y-m-d'), 0);
            }

            $smsRecent = SmsLog::with('patient')->latest()->take(5)->get();
        }

        if ($role === 'Doctor' || $role === 'Nurse') {
            // Doctors & Nurses see today's vaccine schedules (for administering doses)
            $todaySchedules = VaccinationSchedule::with('patient')
                ->where('status', 'scheduled')
                ->whereDate('scheduled_date', $today)
                ->orderBy('dose_number')
                ->take(10)
                ->get();

            $servedTodayCount = Ticket::where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count();

            // Chart for Doctors too (patient volume)
            $monthStart = $today->copy()->startOfMonth();
            $volume = Patient::where('created_at', '>=', $monthStart)
                ->get()
                ->groupBy(fn ($p) => $p->created_at->format('Y-m-d'))
                ->map(fn ($g) => $g->count());

            for ($d = $monthStart->copy(); $d->lte($today); $d->addDay()) {
                $labels[] = $d->format('M d');
                $values[] = $volume->get($d->format('Y-m-d'), 0);
            }
        }

        return view('dashboard', compact(
            'role',
            'patientsToday', 'queueCount', 'totalStock', 'pendingFollowups',
            'waitingPatients', 'currentlyServing', 'completedPatients', 'upcomingVaccinations',
            'overdueDoseCount', 'vaccineStockAlerts',
            'overdue', 'lowStock', 'expiringSoon', 'labels', 'values',
            'recentTickets', 'smsRecent', 'todaySchedules', 'servedTodayCount'
        ));
    }

    public function doctorData(): JsonResponse
    {
        abort_unless(Auth::user()?->role === 'Doctor', 403);

        return response()->json($this->doctorDashboardData(Auth::user()));
    }

    private function doctorDashboardData($doctor): array
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();

        // A ticket's assigned doctor and call/serve timestamps are the existing
        // consultation assignment record; there is no separate consultations table.
        $assignedToday = Ticket::query()
            ->where('assigned_staff_id', $doctor->id)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('called_at', [$start, $end])
                    ->orWhere(function ($fallback) use ($start, $end) {
                        $fallback->whereNull('called_at')->whereBetween('served_at', [$start, $end]);
                    });
            });

        $completedToday = Ticket::query()
            ->where('assigned_staff_id', $doctor->id)
            ->where('status', 'completed')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->whereBetween('completed_at', [$start, $end]);

        $doctorWaiting = Ticket::query()
            ->where('status', 'waiting')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->whereNotNull('assessment_completed_at')
            ->whereDoesntHave('patient.tickets', fn ($query) => $query->whereIn('status', ['called', 'in_progress']))
            ->distinct('patient_id')->count('patient_id');

        $activeStationId = DoctorStation::where('user_id', $doctor->id)->value('id');
        $currentlyServing = $activeStationId ? Ticket::query()
            ->where('assigned_staff_id', $doctor->id)
            ->where('status', 'in_progress')
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->where(function ($query) use ($activeStationId) {
                $query->where('doctor_station_id', $activeStationId)
                    ->orWhere(function ($legacy) use ($activeStationId) {
                        $legacy->whereNull('doctor_station_id')->where('counter_assigned', $activeStationId);
                    });
            })
            ->distinct('patient_id')->count('patient_id') : 0;

        $activityQuery = Ticket::with(['patient', 'doctorStation'])
            ->where('assigned_staff_id', $doctor->id)
            ->whereIn('service_stage', ['assessment', 'medical_review'])
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($completed) use ($start, $end) {
                    $completed->where('status', 'completed')->whereBetween('completed_at', [$start, $end]);
                })->orWhere(function ($active) use ($start, $end) {
                    $active->whereIn('status', ['called', 'in_progress'])
                        ->where(function ($assigned) use ($start, $end) {
                            $assigned->whereBetween('called_at', [$start, $end])
                                ->orWhere(function ($fallback) use ($start, $end) {
                                    $fallback->whereNull('called_at')->whereBetween('served_at', [$start, $end]);
                                })
                                ->orWhere('status', 'in_progress');
                        });
                });
            })
            ->orderByRaw('COALESCE(completed_at, served_at, called_at) DESC')
            ->orderByDesc('updated_at');

        $stationNames = DoctorStation::pluck('name', 'id');
        $activity = $activityQuery->paginate(10)->through(function (Ticket $ticket) use ($stationNames) {
            $stationId = $ticket->doctor_station_id ?? $ticket->counter_assigned;
            $stationName = $ticket->doctorStation?->name
                ?? $stationNames->get($stationId);

            return [
                'ticket_number' => $ticket->ticket_number,
                'patient_name' => $ticket->patient?->fullName() ?? 'Patient record unavailable',
                'station_name' => $stationName ?? 'Station unavailable',
                'status' => match ($ticket->status) {
                    'completed' => 'Completed',
                    'in_progress' => 'In Consultation',
                    'called' => 'Called',
                    default => ucfirst(str_replace('_', ' ', $ticket->status)),
                },
            ];
        });

        return [
            'stats' => [
                'my_patients_today' => (int) (clone $assignedToday)->distinct('patient_id')->count('patient_id'),
                'consultations_completed' => $completedToday->count(),
                'doctor_waiting' => $doctorWaiting,
                'currently_serving' => $currentlyServing,
            ],
            'activity' => $activity,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
