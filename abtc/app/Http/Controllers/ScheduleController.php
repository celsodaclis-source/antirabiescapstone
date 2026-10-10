<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'all');

        $query = VaccinationSchedule::with(['patient', 'administeredBy', 'inventory'])
            ->orderBy('scheduled_date');

        if ($filter === 'upcoming') {
            $query->where('status', 'scheduled')->where('scheduled_date', '>=', now()->toDateString());
        } elseif ($filter === 'overdue') {
            $query->where('status', 'scheduled')->where('scheduled_date', '<', now()->toDateString());
        } elseif ($filter === 'administered') {
            $query->where('status', 'administered');
        } elseif ($filter === 'missed') {
            $query->where('status', 'missed');
        }

        $schedules = $query->paginate(20)->withQueryString();

        $overdueCount = VaccinationSchedule::where('status', 'scheduled')
            ->where('scheduled_date', '<', now()->toDateString())->count();

        return view('schedules.index', compact('schedules', 'filter', 'overdueCount'));
    }

    public function show(Patient $patient): View
    {
        $patient->load(['vaccinationSchedules' => fn ($q) => $q->orderBy('dose_number'), 'biteIncidents']);
        return view('schedules.show', compact('patient'));
    }

    public function edit(VaccinationSchedule $schedule): View
    {
        $schedule->load('patient');
        $batches = VaccineInventory::where('quantity', '>', 0)
            ->where('expiration_date', '>=', now()->toDateString())
            ->orderBy('expiration_date')
            ->get();
        return view('schedules.edit', compact('schedule', 'batches'));
    }

    public function update(Request $request, VaccinationSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'status' => ['required', 'in:scheduled,administered,missed'],
        ]);

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
            'inventory_id' => ['required', 'exists:vaccine_inventory,id'],
        ]);

        // Atomically deduct inventory and record the dose.
        return DB::transaction(function () use ($schedule, $data) {
            $batch = VaccineInventory::lockForUpdate()->find($data['inventory_id']);

            if (! $batch || $batch->quantity <= 0) {
                return back()->with('status', 'Selected vaccine batch is out of stock.')->withInput();
            }

            $batch->decrement('quantity');

            $schedule->update([
                'status' => 'administered',
                'administered_at' => now(),
                'administered_by' => auth()->id(),
                'inventory_id' => $batch->id,
            ]);

            Audit::log('schedule.administer', "Administered dose {$schedule->dose_number} to patient #{$schedule->patient_id} from batch {$batch->batch_number}", $schedule);

            return redirect()->route('schedules.index')
                ->with('status', "Dose {$schedule->dose_number} administered. Batch {$batch->batch_number} stock: {$batch->quantity} remaining.");
        });
    }

    public function overdue(): View
    {
        $overdue = VaccinationSchedule::with('patient')
            ->where('status', 'scheduled')
            ->where('scheduled_date', '<', now()->toDateString())
            ->orderBy('scheduled_date')
            ->paginate(20);

        return view('schedules.overdue', compact('overdue'));
    }
}
