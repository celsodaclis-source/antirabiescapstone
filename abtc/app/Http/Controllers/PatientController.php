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
        $vaccineTypes = $this->availableVaccineTypes();

        return view('patients.create', compact('nextRegularTicketNumber', 'nextPriorityTicketNumber', 'vaccineTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
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
            'exposure_category' => ['required', 'in:I,II,III'],
            'bite_site' => ['nullable', 'string', 'max:150'],
            'animal_status' => ['nullable', 'in:Healthy,Sick,Dead,Unknown'],
            'animal_vaccinated' => ['nullable', 'boolean'],
            'requires_medical_review' => ['nullable', 'boolean'],
            'register_queue' => ['accepted'],
            'patient_type' => ['required', 'string', 'in:'.implode(',', Patient::patientTypes())],
            'vaccine_type' => ['required', 'string', \Illuminate\Validation\Rule::in($this->availableVaccineTypes())],
            'planned_dose_count' => ['required', 'integer', 'in:3,4'],
        ]);

        $animalVaccinated = $request->boolean('animal_vaccinated');

        return DB::transaction(function () use ($data, $animalVaccinated, $request) {
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
                'vaccine_type' => $data['vaccine_type'],
                'planned_dose_count' => (int) $data['planned_dose_count'],
            ]);

            BiteIncident::create([
                'patient_id' => $patient->id,
                'date_of_bite' => $data['date_of_bite'],
                'animal_type' => $data['animal_type'],
                'exposure_category' => $data['exposure_category'],
                'bite_site' => data_get($data, 'bite_site'),
                'animal_status' => data_get($data, 'animal_status'),
                'animal_vaccinated' => $animalVaccinated,
            ]);

            $ticket = QueueTicketNumber::forNewPatient($patient, $request->boolean('requires_medical_review'));

            $this->generateSchedules($patient, $data['date_of_bite'], (int) $data['planned_dose_count']);

            Audit::log('patient.register', "Registered patient {$patient->fullName()} (Ticket: ".($ticket ? $ticket->ticket_number : 'none').")", $patient);

            return redirect()->route('patients.show', $patient)
                ->with('status', 'Patient registered successfully.'.( $ticket ? " Ticket {$ticket->ticket_number} generated." : ''));
        });
    }

    public function show(Patient $patient): View
    {
        $patient->load(['biteIncidents', 'tickets', 'vaccinationSchedules' => fn ($q) => $q->orderBy('dose_number')]);
        return view('patients.show', compact('patient'));
    }

    public function edit(Patient $patient): View
    {
        return view('patients.edit', compact('patient'));
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
