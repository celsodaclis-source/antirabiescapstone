<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use App\Models\SmsLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $role = $user->role;
        $today = Carbon::today();

        // ── Common KPIs ──
        $patientsToday = Patient::whereDate('created_at', $today)->count();
        $queueCount = Ticket::whereIn('status', ['waiting', 'called', 'in_progress', 'held'])->count();
        $totalStock = VaccineInventory::sum('quantity');
        $pendingFollowups = VaccinationSchedule::where('status', 'scheduled')
            ->where('scheduled_date', '<=', $today)->count();

        // ── Overdue doses (relevant to all roles) ──
        $overdue = VaccinationSchedule::with('patient')
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<', $today)
            ->orderBy('scheduled_date')
            ->get();

        // ── Active queue (relevant to all roles) ──
        $recentTickets = Ticket::with(['patient', 'assignedStaff'])
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
            'overdue', 'lowStock', 'expiringSoon', 'labels', 'values',
            'recentTickets', 'smsRecent', 'todaySchedules', 'servedTodayCount'
        ));
    }
}
