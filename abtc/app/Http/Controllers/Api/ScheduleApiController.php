<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Models\Ticket;
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
            $query->whereIn('status', ['scheduled', 'missed'])->where('scheduled_date', '<', now()->toDateString());
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
            ->whereIn('status', ['scheduled', 'missed'])
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
            'vaccine_type' => ['required', 'string', Rule::in($this->availableVaccineTypes($schedule))],
            'inventory_id' => ['required', 'exists:vaccine_inventory,id'],
        ]);

        return DB::transaction(function () use ($schedule, $data) {
            $lockedSchedule = VaccinationSchedule::lockForUpdate()->findOrFail($schedule->id);
            if ($lockedSchedule->status === 'administered') {
                return response()->json(['message' => 'This dose was already administered.'], 422);
            }
            if ($lockedSchedule->scheduled_date->gt(today())) {
                return response()->json(['message' => 'This dose is not due yet.'], 422);
            }
            if (VaccinationSchedule::where('patient_id', $lockedSchedule->patient_id)
                ->where('dose_number', '<', $lockedSchedule->dose_number)
                ->where('status', '!=', 'administered')->exists()) {
                return response()->json(['message' => 'Earlier doses must be administered first.'], 422);
            }

            $batch = VaccineInventory::lockForUpdate()
                ->where('vaccine_name', $data['vaccine_type'])
                ->where('quantity', '>', 0)
                ->where('expiration_date', '>=', now()->toDateString())
                ->find($data['inventory_id']);

            if (!$batch || $this->isImmunoglobulin($batch->vaccine_name)) {
                return response()->json(['message' => 'Select an in-stock, unexpired batch matching the vaccine type.'], 422);
            }

            $batch->decrement('quantity');

            $lockedSchedule->update([
                'status' => 'administered',
                'administered_at' => now(),
                'administered_by' => auth()->id(),
                'inventory_id' => $batch->id,
            ]);

            Ticket::where('vaccination_schedule_id', $lockedSchedule->id)
                ->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])
                ->update(['status' => 'completed', 'completed_at' => now()]);

            Audit::log('schedule.administer', "API: Administered dose {$lockedSchedule->dose_number} to patient #{$lockedSchedule->patient_id} from batch {$batch->batch_number}", $lockedSchedule);

            return response()->json([
                'data' => $lockedSchedule->load(['patient', 'administeredBy', 'inventory']),
                'message' => "Dose {$lockedSchedule->dose_number} administered from batch {$batch->batch_number}.",
            ]);
        });
    }

    protected function availableVaccineTypes(?VaccinationSchedule $schedule = null): array
    {
        $query = VaccineInventory::where('quantity', '>', 0)
            ->where('expiration_date', '>=', now()->toDateString())
            ->when($schedule?->patient?->vaccine_type, fn ($q, $vaccineType) => $q->where('vaccine_name', $vaccineType));

        return $query->pluck('vaccine_name')
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

