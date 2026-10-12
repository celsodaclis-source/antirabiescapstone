<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = $request->input('filter', 'all');
        $query = VaccinationSchedule::with(['patient', 'administeredBy', 'inventory'])->orderBy('scheduled_date');

        if ($filter === 'upcoming') {
            $query->where('status', 'scheduled')->where('scheduled_date', '>=', now()->toDateString());
        } elseif ($filter === 'overdue') {
            $query->where('status', 'scheduled')->where('scheduled_date', '<', now()->toDateString());
        } elseif ($filter === 'administered') {
            $query->where('status', 'administered');
        } elseif ($filter === 'missed') {
            $query->where('status', 'missed');
        }

        $schedules = $query->paginate($request->input('per_page', 20));

        return response()->json($schedules);
    }

    public function byPatient(Patient $patient): JsonResponse
    {
        $schedules = $patient->vaccinationSchedules()->orderBy('dose_number')->get();
        return response()->json(['data' => $schedules]);
    }

    public function overdue(): JsonResponse
    {
        $overdue = VaccinationSchedule::with('patient')
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<', now()->toDateString())
            ->orderBy('scheduled_date')
            ->get();

        return response()->json([
            'data' => $overdue,
            'count' => $overdue->count(),
        ]);
    }

    public function administer(Request $request, VaccinationSchedule $schedule): JsonResponse
    {
        if ($schedule->status === 'administered') {
            return response()->json(['message' => 'This dose was already administered.'], 422);
        }

        $data = $request->validate([
            'vaccine_type' => ['required', 'string', Rule::in($this->availableVaccineTypes())],
            'inventory_id' => ['required', 'exists:vaccine_inventory,id'],
        ]);

        return DB::transaction(function () use ($schedule, $data) {
            $batch = VaccineInventory::lockForUpdate()
                ->where('vaccine_name', $data['vaccine_type'])
                ->where('quantity', '>', 0)
                ->where('expiration_date', '>=', now()->toDateString())
                ->find($data['inventory_id']);

            if (!$batch || $this->isImmunoglobulin($batch->vaccine_name)) {
                return response()->json(['message' => 'Select an in-stock, unexpired batch matching the vaccine type.'], 422);
            }

            $batch->decrement('quantity');

            $schedule->update([
                'status' => 'administered',
                'administered_at' => now(),
                'administered_by' => auth()->id(),
                'inventory_id' => $batch->id,
            ]);

            Audit::log('schedule.administer', "API: Administered dose {$schedule->dose_number} to patient #{$schedule->patient_id} from batch {$batch->batch_number}", $schedule);

            return response()->json([
                'data' => $schedule->load(['patient', 'administeredBy', 'inventory']),
                'message' => "Dose {$schedule->dose_number} administered from batch {$batch->batch_number}.",
            ]);
        });
    }

    protected function availableVaccineTypes(): array
    {
        return VaccineInventory::where('quantity', '>', 0)
            ->where('expiration_date', '>=', now()->toDateString())
            ->pluck('vaccine_name')
            ->unique()
            ->reject(fn (string $name) => $this->isImmunoglobulin($name))
            ->values()
            ->all();
    }

    protected function isImmunoglobulin(string $productName): bool
    {
        return (bool) preg_match(
            '/\b(?:equi[\s-]?rab|(?:human|equine)\s+rabies\s+(?:immunoglobulin|serum)|anti[- ]rabies\s+serum|rabies\s+(?:immunoglobulin|immune\s+globulin)|(?:hrig|erig|rig))\b/i',
            $productName
        );
    }
}

