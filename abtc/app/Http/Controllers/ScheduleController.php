<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'all');
        $search = trim((string) $request->input('search', ''));

        $query = VaccinationSchedule::with(['patient', 'administeredBy', 'inventory', 'tickets'])
            ->orderBy('scheduled_date');

        if ($search !== '') {
            $searchTerms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            $query->whereHas('patient', function ($patientQuery) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $patientQuery->where(function ($nameQuery) use ($term) {
                        $nameQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('middle_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
                }
            });
        }

        if ($filter === 'upcoming') {
            $query->where('status', 'scheduled')->where('scheduled_date', '>=', now()->toDateString());
        } elseif ($filter === 'overdue') {
            $query->whereIn('status', ['scheduled', 'missed'])->where('scheduled_date', '<', now()->toDateString());
        } elseif ($filter === 'administered') {
            $query->where('status', 'administered');
        } elseif ($filter === 'missed') {
            $query->where('status', 'missed');
        }

        $schedules = $query->paginate(20)->withQueryString();

        $overdueCount = VaccinationSchedule::whereIn('status', ['scheduled', 'missed'])
            ->where('scheduled_date', '<', now()->toDateString())->count();

        return view('schedules.index', compact('schedules', 'filter', 'overdueCount', 'search'));
    }

    public function show(Patient $patient): View
    {
        $patient->load(['vaccinationSchedules' => fn ($q) => $q->orderBy('dose_number'), 'biteIncidents']);
        return view('schedules.show', compact('patient'));
    }

    public function edit(VaccinationSchedule $schedule): View
    {
        $schedule->load('patient.biteIncidents');
        $batches = VaccineInventory::where('quantity', '>', 0)
            ->where('expiration_date', '>=', now()->toDateString())
            ->when($schedule->patient?->vaccine_type, fn ($q, $vaccineType) => $q->where('vaccine_name', $vaccineType))
            ->orderBy('expiration_date')
            ->get()
            ->reject(fn (VaccineInventory $batch) => $this->isImmunoglobulin($batch->vaccine_name))
            ->values();
        $vaccineTypes = $batches->pluck('vaccine_name')->unique()->values();

        return view('schedules.edit', compact('schedule', 'batches', 'vaccineTypes'));
    }

    public function update(Request $request, VaccinationSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'status' => ['required', Rule::in($schedule->status === 'administered' ? ['administered'] : ['scheduled', 'missed'])],
        ]);

        if ($data['status'] === 'missed' && \Illuminate\Support\Carbon::parse($data['scheduled_date'])->gt(today())) {
            return back()->withErrors(['status' => 'A dose can only be marked missed after its scheduled date.'])->withInput();
        }

        $schedule->update($data);
        Audit::log('schedule.reschedule', "Rescheduled dose {$schedule->dose_number} for patient #{$schedule->patient_id} to {$schedule->scheduled_date}", $schedule);

        return redirect()->route('schedules.index')->with('status', 'Appointment updated.');
    }

    public function administer(Request $request, VaccinationSchedule $schedule): RedirectResponse
    {
        if ($schedule->status === 'administered') {
            return back()->with('status', 'This dose was already administered.');
        }

        $data = $request->validate([
            'vaccine_type' => ['required', 'string', Rule::in($this->availableVaccineTypes($schedule))],
            'inventory_id' => ['required', 'exists:vaccine_inventory,id'],
        ]);

        // Atomically deduct inventory and record the dose.
        return DB::transaction(function () use ($schedule, $data) {
            $lockedSchedule = VaccinationSchedule::lockForUpdate()->findOrFail($schedule->id);
            if ($lockedSchedule->status === 'administered') {
                return back()->with('status', 'This dose was already administered.');
            }
            if ($lockedSchedule->scheduled_date->gt(today())) {
                return back()->withErrors(['scheduled_date' => 'This dose is not due yet.'])->withInput();
            }
            if (VaccinationSchedule::where('patient_id', $lockedSchedule->patient_id)
                ->where('dose_number', '<', $lockedSchedule->dose_number)
                ->where('status', '!=', 'administered')->exists()) {
                return back()->withErrors(['schedule' => 'Earlier doses must be administered first.'])->withInput();
            }

            $batch = VaccineInventory::lockForUpdate()
                ->where('vaccine_name', $data['vaccine_type'])
                ->where('quantity', '>', 0)
                ->where('expiration_date', '>=', now()->toDateString())
                ->find($data['inventory_id']);

            if (! $batch || $this->isImmunoglobulin($batch->vaccine_name)) {
                return back()->withErrors(['inventory_id' => 'Select an in-stock, unexpired batch matching the vaccine type.'])->withInput();
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

            Audit::log('schedule.administer', "Administered dose {$lockedSchedule->dose_number} to patient #{$lockedSchedule->patient_id} from batch {$batch->batch_number}", $lockedSchedule);

            return redirect()->route('schedules.index')
                ->with('status', "Dose {$schedule->dose_number} administered. Batch {$batch->batch_number} stock: {$batch->quantity} remaining.");
        });
    }

    public function overdue(): View
    {
        $overdue = VaccinationSchedule::with('patient')
            ->whereIn('status', ['scheduled', 'missed'])
            ->where('scheduled_date', '<', now()->toDateString())
            ->orderBy('scheduled_date')
            ->paginate(20);

        return view('schedules.overdue', compact('overdue'));
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
