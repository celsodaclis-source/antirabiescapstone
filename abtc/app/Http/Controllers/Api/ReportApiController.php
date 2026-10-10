<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Models\BiteIncident;
use App\Models\SmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportApiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $fromDt = \Carbon\Carbon::parse($from)->startOfDay();
        $toDt = \Carbon\Carbon::parse($to)->endOfDay();

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'stats' => [
                'patients_registered' => Patient::whereBetween('created_at', [$fromDt, $toDt])->count(),
                'patients_served' => Ticket::where('status', 'completed')
                    ->whereBetween('completed_at', [$fromDt, $toDt])->count(),
                'vaccinations_administered' => VaccinationSchedule::where('status', 'administered')
                    ->whereBetween('administered_at', [$fromDt, $toDt])->count(),
                'missed_appointments' => VaccinationSchedule::where('status', 'missed')
                    ->whereBetween('scheduled_date', [$fromDt, $toDt])->count(),
                'overdue_followups' => VaccinationSchedule::where('status', 'scheduled')
                    ->where('scheduled_date', '<', now()->toDateString())->count(),
                'total_inventory_stock' => VaccineInventory::sum('quantity'),
                'sms_delivered' => SmsLog::where('status', 'delivered')
                    ->whereBetween('created_at', [$fromDt, $toDt])->count(),
            ],
        ]);
    }

    public function barangayStats(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $barangays = Patient::whereBetween('created_at', [
                \Carbon\Carbon::parse($from)->startOfDay(),
                \Carbon\Carbon::parse($to)->endOfDay(),
            ])
            ->get()
            ->groupBy('barangay')
            ->map(fn ($g) => [
                'barangay' => $g->first()->barangay,
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->values();

        return response()->json(['data' => $barangays]);
    }

    public function exposureBreakdown(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $breakdown = BiteIncident::whereBetween('created_at', [
                \Carbon\Carbon::parse($from)->startOfDay(),
                \Carbon\Carbon::parse($to)->endOfDay(),
            ])
            ->get()
            ->groupBy('exposure_category')
            ->map(fn ($g) => [
                'category' => $g->first()->exposure_category,
                'count' => $g->count(),
            ])
            ->sortBy('category')
            ->values();

        $byAnimal = BiteIncident::whereBetween('created_at', [
                \Carbon\Carbon::parse($from)->startOfDay(),
                \Carbon\Carbon::parse($to)->endOfDay(),
            ])
            ->get()
            ->groupBy('animal_type')
            ->map(fn ($g) => [
                'animal' => $g->first()->animal_type,
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->values();

        return response()->json([
            'by_category' => $breakdown,
            'by_animal' => $byAnimal,
        ]);
    }
}

