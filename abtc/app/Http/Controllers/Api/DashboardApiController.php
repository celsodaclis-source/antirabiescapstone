<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccineInventory;
use App\Models\VaccinationSchedule;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardApiController extends Controller
{
    public function index(): JsonResponse
    {
        $today = Carbon::today();

        $patientsToday = Patient::whereDate('created_at', $today)->count();
        $queueCount = Ticket::whereIn('status', ['waiting', 'called', 'in_progress', 'held'])->count();
        $totalStock = VaccineInventory::sum('quantity');
        $pendingFollowups = VaccinationSchedule::whereIn('status', ['scheduled', 'missed'])
            ->where('scheduled_date', '<=', $today)->count();

        $overdue = VaccinationSchedule::with('patient')
            ->whereIn('status', ['scheduled', 'missed'])
            ->where('scheduled_date', '<', $today)
            ->orderBy('scheduled_date')
            ->get();

        $lowStock = VaccineInventory::whereRaw('quantity <= low_stock_threshold')->get();
        $expiringSoon = VaccineInventory::where('expiration_date', '<=', $today->copy()->addDays(30))
            ->where('expiration_date', '>=', $today)
            ->get();

        // Patient volume for current month
        $monthStart = $today->copy()->startOfMonth();
        $volume = Patient::where('created_at', '>=', $monthStart)
            ->get()
            ->groupBy(fn ($p) => $p->created_at->format('Y-m-d'))
            ->map(fn ($g) => $g->count());

        $labels = [];
        $values = [];
        for ($d = $monthStart->copy(); $d->lte($today); $d->addDay()) {
            $labels[] = $d->format('M d');
            $values[] = $volume->get($d->format('Y-m-d'), 0);
        }

        $recentTickets = Ticket::with(['patient'])
            ->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])
            ->orderBy('created_at')
            ->take(8)
            ->get();

        $smsRecent = SmsLog::with('patient')->latest()->take(5)->get();

        return response()->json([
            'stats' => [
                'patients_today' => $patientsToday,
                'queue_count' => $queueCount,
                'total_stock' => $totalStock,
                'pending_followups' => $pendingFollowups,
            ],
            'alerts' => [
                'overdue_count' => $overdue->count(),
                'low_stock_count' => $lowStock->count(),
                'expiring_soon_count' => $expiringSoon->count(),
            ],
            'chart' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'recent_tickets' => $recentTickets,
            'recent_sms' => $smsRecent,
        ]);
    }
}

