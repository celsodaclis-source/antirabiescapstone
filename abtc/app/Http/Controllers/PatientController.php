<?php

namespace App\Http\Controllers;

use App\Models\BiteIncident;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Services\Audit;
use App\Services\QueueTicketNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $query = Patient::query()->with(['biteIncidents', 'latestTicket']);

        $search = $request->input('search', '');
        $filterBarangay = $request->input('barangay', '');
        $filterType = $request->input('patient_type', '');

        // Sorting
        $sortField = $request->input('sort', 'last_name');
        $sortDir = $request->input('direction', 'asc');

        $allowedSorts = ['first_name', 'last_name', 'sex', 'barangay', 'patient_type', 'created_at'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'last_name';
        }
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere(DB::raw("first_name || ' ' || last_name"), 'like', "%{$search}%")
                  ->orWhere(DB::raw("first_name || ' ' || middle_name || ' ' || last_name"), 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhereHas('tickets', function ($tq) use ($search) {
                      $tq->where('ticket_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($filterBarangay) {
            $query->where('barangay', $filterBarangay);
        }

        if ($filterType) {
            $query->where('patient_type', $filterType);
        }

        if ($sortField === 'last_name') {
            $patients = $query->orderBy('last_name', $sortDir)->orderBy('first_name', $sortDir)->paginate(15)->withQueryString();
        } else {
            $patients = $query->orderBy($sortField, $sortDir)->paginate(15)->withQueryString();
        }

        $stats = [
            'total' => Patient::count(),
            'today' => Patient::whereDate('created_at', today())->count(),
            'priority' => Patient::where('patient_type', '!=', 'regular')->count(),
        ];

        $barangays = Patient::select('barangay')->distinct()->orderBy('barangay')->pluck('barangay');
        $patientTypes = Patient::patientTypes();

        return view('patients.index', compact('patients', 'search', 'filterBarangay', 'filterType', 'stats', 'barangays', 'patientTypes', 'sortField', 'sortDir'));
    }

    public function create(): View
    {
        $nextRegularTicketNumber = $this->nextTicketNumber();
        $nextPriorityTicketNumber = $this->nextTicketNumber(true);

        return view('patients.create', compact('nextRegularTicketNumber', 'nextPriorityTicketNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isClinical = $this->isClinicalUser($request);
        $isClinicalAssessment = in_array($request->user()->role, ['Staff', 'Doctor', 'Nurse'], true);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'in:'.implode(',', Patient::suffixes())],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', 'in:Male,Female,Other'],
            'contact_number' => ['nullable', 'string', 'regex:/^\\d{11}$/'],
            'address' => ['required', 'string'],
            'barangay' => ['required', 'string', 'max:100', 'in:'.implode(',', Patient::barangays())],
            'date_of_bite' => ['required', 'date', 'before_or_equal:today'],
            'animal_type' => ['required', 'string', 'max:100'],
            'exposure_category' => $isClinicalAssessment ? ['nullable', 'in:I,II,III'] : ['required', 'in:I,II,III'],
            'bite_site' => ['nullable', 'string', 'max:150'],
            'animal_status' => ['nullable', 'in:Healthy,Sick,Dead,Unknown'],
            'animal_vaccinated' => ['nullable', 'boolean'],
            'requires_medical_review' => ['nullable', 'boolean'],
            'register_queue' => ['accepted'],
            'patient_type' => ['required', 'string', 'in:'.implode(',', Patient::patientTypes())],
        ]);

        $animalVaccinated = $request->boolean('animal_vaccinated');
        $requiresMedicalReview = $isClinical && $request->boolean('requires_medical_review');

        if ($this->findDuplicatePatient($data)) {
            return back()->withInput()->withErrors([
                'first_name' => 'A patient with this name and date of birth already exists. Search for the existing patient and create a returning-patient ticket instead.',
            ]);
        }

        return DB::transaction(function () use ($data, $animalVaccinated, $request, $isClinical, $requiresMedicalReview) {
            if ($this->findDuplicatePatient($data)) {
                throw ValidationException::withMessages([
                    'first_name' => 'A patient with this name and date of birth already exists. Search for the existing patient and create a returning-patient ticket instead.',
                ]);
            }

            $patient = Patient::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => data_get($data, 'middle_name'),
                'suffix' => data_get($data, 'suffix'),
                'dob' => $data['dob'],
                'sex' => $data['sex'],
                'contact_number' => $data['contact_number'],
                'address' => $data['address'],
                'barangay' => $data['barangay'],
                'patient_type' => $data['patient_type'] ?? 'regular',
            ]);

            BiteIncident::create([
                'patient_id' => $patient->id,
                'date_of_bite' => $data['date_of_bite'],
                'animal_type' => $data['animal_type'],
                'exposure_category' => data_get($data, 'exposure_category'),
                'bite_site' => data_get($data, 'bite_site'),
                'animal_status' => data_get($data, 'animal_status'),
                'animal_vaccinated' => $animalVaccinated,
            ]);

            $ticket = QueueTicketNumber::forNewPatient($patient, $requiresMedicalReview);

            Audit::log('patient.register', "Registered patient {$patient->fullName()} (Ticket: ".($ticket ? $ticket->ticket_number : 'none').")", $patient);

            if ($request->user()->role === 'Staff') {
                return redirect()->route('staff.tickets.print', $ticket)
                    ->with('status', "Patient registered successfully. Ticket {$ticket->ticket_number} generated.");
            }

            return redirect()->route('patients.show', $patient)
                ->with('status', "Patient registered successfully. Ticket {$ticket->ticket_number} generated.");
        });
    }

    public function plan(Patient $patient): View|RedirectResponse
    {
        $patient->load(['biteIncidents' => fn ($query) => $query->orderBy('date_of_bite')->orderBy('id')]);
        $vaccineTypes = $this->availableVaccineTypes();

        return view('patients.plan', compact('patient', 'vaccineTypes'));
    }

    public function storePlan(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($request->user()->role === 'Doctor', 403);

        $biteIncident = $patient->biteIncidents()->orderBy('date_of_bite')->first();
        if (! $biteIncident) {
            return redirect()->route('patients.show', $patient)
                ->withErrors(['exposure_category' => 'No bite incident is recorded for this patient.']);
        }

        $data = $request->validate([
            'exposure_category' => ['required', 'in:I,II,III'],
            'schedule' => ['required', 'in:days_0_3_7,days_0_3_7_28'],
            'vaccine_type' => ['required', 'string', \Illuminate\Validation\Rule::in($this->availableVaccineTypes())],
            'planned_dose_count' => ['required', 'integer', 'in:3,4'],
            'requires_medical_review' => ['nullable', 'boolean'],
        ]);

        $scheduleDoseCount = $data['schedule'] === 'days_0_3_7' ? 3 : 4;
        if ((int) $data['planned_dose_count'] !== $scheduleDoseCount) {
            throw ValidationException::withMessages([
                'planned_dose_count' => 'The selected schedule and number of doses must match.',
            ]);
        }

        DB::transaction(function () use ($patient, $data, $request, $biteIncident) {
            $existingSchedules = $patient->vaccinationSchedules()
                ->withCount('tickets')
                ->orderBy('dose_number')
                ->lockForUpdate()
                ->get();
            $scheduleCountChanged = $existingSchedules->isNotEmpty()
                && (int) $patient->planned_dose_count !== (int) $data['planned_dose_count'];

            if ($scheduleCountChanged && $existingSchedules->contains(
                fn (VaccinationSchedule $schedule) => $schedule->status !== 'scheduled' || $schedule->tickets_count > 0
            )) {
                throw ValidationException::withMessages([
                    'planned_dose_count' => 'The schedule cannot be changed after doses have been administered, missed, or queued.',
                ]);
            }

            $biteIncident->update(['exposure_category' => $data['exposure_category']]);
            $patient->update([
                'vaccine_type' => $data['vaccine_type'],
                'planned_dose_count' => (int) $data['planned_dose_count'],
            ]);

            if ($existingSchedules->isEmpty()) {
                $this->generateSchedules($patient, $biteIncident->date_of_bite->toDateString(), (int) $data['planned_dose_count']);
            } elseif ($scheduleCountChanged) {
                $doseDays = array_slice(VaccinationSchedule::DOSE_DAYS, 0, (int) $data['planned_dose_count']);
                foreach ($existingSchedules as $schedule) {
                    if ($schedule->dose_number > count($doseDays)) {
                        $schedule->delete();
                        continue;
                    }

                    $schedule->update([
                        'scheduled_date' => $biteIncident->date_of_bite->copy()->addDays($doseDays[$schedule->dose_number - 1]),
                    ]);
                }

                foreach ($doseDays as $index => $dayOffset) {
                    if (! $existingSchedules->contains('dose_number', $index + 1)) {
                        VaccinationSchedule::create([
                            'patient_id' => $patient->id,
                            'dose_number' => $index + 1,
                            'scheduled_date' => $biteIncident->date_of_bite->copy()->addDays($dayOffset),
                            'status' => 'scheduled',
                        ]);
                    }
                }
            }

            if ($request->boolean('requires_medical_review')) {
                Ticket::where('patient_id', $patient->id)
                    ->whereIn('status', ['waiting', 'called', 'in_progress', 'held'])
                    ->update(['service_stage' => 'medical_review']);
            }

            Audit::log('patient.plan', "Recorded vaccination plan for {$patient->fullName()} ({$data['vaccine_type']}, {$data['planned_dose_count']} doses)", $patient);
        });

        return redirect()->route('patients.show', $patient)
            ->with('status', 'Vaccination plan recorded. The dose schedule has been generated.');
    }

    public function show(Patient $patient): View
    {
        $patient->load(['biteIncidents', 'tickets', 'vaccinationSchedules' => fn ($q) => $q->orderBy('dose_number')]);
        return view('patients.show', compact('patient'));
    }

    public function edit(Patient $patient): View
    {
        $patient->load(['biteIncidents' => fn ($query) => $query->orderBy('date_of_bite')->orderBy('id')]);
        $vaccineTypes = auth()->user()?->role === 'Doctor' ? $this->availableVaccineTypes() : [];

        return view('patients.edit', compact('patient', 'vaccineTypes'));
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $patientTypes = Patient::patientTypes();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'in:'.implode(',', Patient::suffixes())],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', 'in:Male,Female,Other'],
            'patient_type' => ['nullable', 'string', 'in:'.implode(',', $patientTypes)],
            'contact_number' => ['nullable', 'string', 'regex:/^\\d{11}$/'],
            'address' => ['required', 'string'],
            'barangay' => ['required', 'string', 'max:100', 'in:'.implode(',', Patient::barangays())],
        ]);

        $patient->update($data);
        Audit::log('patient.update', "Updated patient {$patient->fullName()}", $patient);

        return redirect()->route('patients.show', $patient)->with('status', 'Patient record updated.');
    }

    public function search(Request $request)
    {
        $term = $request->input('term', '');
        $patients = Patient::where('first_name', 'like', "%{$term}%")
            ->orWhere('last_name', 'like', "%{$term}%")
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'middle_name', 'contact_number', 'barangay']);

        return response()->json($patients->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->fullName(),
            'contact' => $p->contact_number,
            'barangay' => $p->barangay,
        ]));
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $name = $patient->fullName();

        DB::transaction(function () use ($patient) {
            // Soft-delete related records
            $patient->biteIncidents()->delete();
            $patient->tickets()->delete();
            $patient->vaccinationSchedules()->delete();
            $patient->delete(); // soft-delete patient
        });

        Audit::log('patient.delete', "Deleted patient {$name} and related records", $patient);

        return redirect()->route('patients.index')
            ->with('status', "Patient {$name} has been deleted successfully.");
    }

    public function exportCsv(Request $request)
    {
        $query = Patient::query()->with(['latestTicket']);

        // Apply same filters as index
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }
        if ($barangay = $request->input('barangay')) {
            $query->where('barangay', $barangay);
        }
        if ($type = $request->input('patient_type')) {
            $query->where('patient_type', $type);
        }

        $patients = $query->orderBy('last_name')->orderBy('first_name')->get();

        $filename = 'patients-export-'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $columns = ['Last Name', 'First Name', 'Middle Name', 'Suffix', 'Date of Birth', 'Sex', 'Patient Type', 'Contact Number', 'Address', 'Barangay', 'Registered Date', 'Last Ticket'];

        $callback = function () use ($patients, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($patients as $p) {
                fputcsv($file, [
                    $p->last_name,
                    $p->first_name,
                    $p->middle_name ?? '',
                    $p->suffix ?? '',
                    $p->dob->format('Y-m-d'),
                    $p->sex,
                    $p->patientTypeLabel(),
                    $p->contact_number ?? '',
                    $p->address,
                    $p->barangay,
                    $p->created_at->format('Y-m-d'),
                    $p->latestTicket?->ticket_number ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function history(Patient $patient)
    {
        $patient->load(['biteIncidents', 'tickets', 'vaccinationSchedules']);
        return response()->json([
            'patient' => $patient->only(['id', 'first_name', 'last_name', 'middle_name', 'contact_number', 'barangay']),
            'bite_incidents' => $patient->biteIncidents,
            'tickets' => $patient->tickets,
            'schedules' => $patient->vaccinationSchedules,
        ]);
    }

    protected function nextTicketNumber(bool $isPriority = false): string
    {
        return QueueTicketNumber::next($isPriority);
    }

    protected function isClinicalUser(Request $request): bool
    {
        return in_array($request->user()?->role, ['Doctor', 'Nurse'], true);
    }

    private function findDuplicatePatient(array $data): ?Patient
    {
        return Patient::query()
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower(trim($data['first_name']))])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower(trim($data['last_name']))])
            ->whereDate('dob', $data['dob'])
            ->where(function ($query) use ($data) {
                $middleName = trim((string) ($data['middle_name'] ?? ''));
                if ($middleName === '') {
                    $query->whereNull('middle_name')->orWhere('middle_name', '');
                } else {
                    $query->whereRaw('LOWER(TRIM(middle_name)) = ?', [mb_strtolower($middleName)]);
                }
            })
            ->first();
    }

    protected function generateSchedules(Patient $patient, string $biteDate, int $doseCount = 4): void
    {
        $base = \Carbon\Carbon::parse($biteDate);
        foreach (array_slice(VaccinationSchedule::DOSE_DAYS, 0, $doseCount) as $index => $dayOffset) {
            VaccinationSchedule::create([
                'patient_id' => $patient->id,
                'dose_number' => $index + 1,
                'scheduled_date' => $base->copy()->addDays($dayOffset),
                'status' => 'scheduled',
            ]);
        }
    }

    protected function availableVaccineTypes(): array
    {
        return VaccineInventory::where('quantity', '>', 0)
            ->where('expiration_date', '>=', now()->toDateString())
            ->pluck('vaccine_name')
            ->unique()
            ->reject(fn (string $name) => (bool) preg_match(
                '/\b(?:equi[\s-]?rab|(?:human|equine)\s+rabies\s+(?:immunoglobulin|serum)|anti[- ]rabies\s+serum|rabies\s+(?:immunoglobulin|immune\s+globulin)|(?:hrig|erig|rig))\b/i',
                $name
            ))
            ->values()
            ->all();
    }
}
