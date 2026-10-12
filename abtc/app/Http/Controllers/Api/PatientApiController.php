<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiteIncident;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Patient::with(['biteIncidents', 'latestTicket']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere(DB::raw("first_name || ' ' || last_name"), 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('last_name')->orderBy('first_name')->paginate($request->input('per_page', 15));

        return response()->json($patients);
    }

    public function show(Patient $patient): JsonResponse
    {
        $patient->load(['biteIncidents', 'tickets', 'vaccinationSchedules' => fn ($q) => $q->orderBy('dose_number')]);
        return response()->json(['data' => $patient]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'in:' . implode(',', Patient::suffixes())],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', 'in:Male,Female,Other'],
            'contact_number' => ['nullable', 'string', 'regex:/^\\d{11}$/'],
            'address' => ['required', 'string'],
            'barangay' => ['required', 'string', 'max:100', 'in:' . implode(',', Patient::barangays())],
            'date_of_bite' => ['required', 'date', 'before_or_equal:today'],
            'animal_type' => ['required', 'string', 'max:100'],
            'exposure_category' => ['required', 'in:I,II,III'],
            'bite_site' => ['nullable', 'string', 'max:150'],
            'animal_status' => ['nullable', 'in:Healthy,Sick,Dead,Unknown'],
            'animal_vaccinated' => ['nullable', 'boolean'],
            'register_queue' => ['accepted'],
            'patient_type' => ['required', 'string', 'in:' . implode(',', Patient::patientTypes())],
            'vaccine_type' => ['required', 'string', \Illuminate\Validation\Rule::in($this->availableVaccineTypes())],
            'planned_dose_count' => ['required', 'integer', 'in:3,4'],
        ]);

        return DB::transaction(function () use ($data, $request) {
            $patient = Patient::create([...$data, 'patient_type' => $data['patient_type'] ?? 'regular']);

            BiteIncident::create([
                'patient_id' => $patient->id,
                'date_of_bite' => $data['date_of_bite'],
                'animal_type' => $data['animal_type'],
                'exposure_category' => $data['exposure_category'],
                'bite_site' => $data['bite_site'] ?? null,
                'animal_status' => $data['animal_status'] ?? null,
                'animal_vaccinated' => $request->boolean('animal_vaccinated'),
            ]);

            $ticketNumber = $this->nextTicketNumber($patient->isPriority());
            $ticket = Ticket::create([
                'patient_id' => $patient->id,
                'ticket_number' => $ticketNumber,
                'status' => 'waiting',
                'is_priority' => $patient->isPriority(),
            ]);

            $this->generateSchedules($patient, $data['date_of_bite'], (int) $data['planned_dose_count']);

            Audit::log('patient.register', "API: Registered patient {$patient->fullName()} (Ticket: " . ($ticket ? $ticket->ticket_number : 'none') . ")", $patient);

            return response()->json([
                'data' => $patient->load('biteIncidents', 'tickets', 'vaccinationSchedules'),
                'ticket' => $ticket,
                'message' => 'Patient registered successfully.',
            ], 201);
        });
    }

    public function update(Request $request, Patient $patient): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'in:' . implode(',', Patient::suffixes())],
            'dob' => ['sometimes', 'date', 'before_or_equal:today'],
            'sex' => ['sometimes', 'in:Male,Female,Other'],
            'patient_type' => ['nullable', 'string', 'in:' . implode(',', Patient::patientTypes())],
            'contact_number' => ['nullable', 'string', 'regex:/^\\d{11}$/'],
            'address' => ['sometimes', 'string'],
            'barangay' => ['sometimes', 'string', 'max:100', 'in:' . implode(',', Patient::barangays())],
        ]);

        $patient->update($data);
        Audit::log('patient.update', "API: Updated patient {$patient->fullName()}", $patient);

        return response()->json(['data' => $patient, 'message' => 'Patient updated.']);
    }

    public function destroy(Patient $patient): JsonResponse
    {
        Audit::log('patient.delete', "API: Deleted patient {$patient->fullName()}", $patient);
        $patient->delete();

        return response()->json(['message' => 'Patient deleted.']);
    }

    public function search(Request $request): JsonResponse
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

    public function history(Patient $patient): JsonResponse
    {
        $patient->load(['biteIncidents', 'tickets', 'vaccinationSchedules']);
        return response()->json(['data' => $patient]);
    }

    protected function nextTicketNumber(bool $isPriority = false): string
    {
        $prefix = $isPriority ? 'P' : 'A';
        $last = Ticket::where('ticket_number', 'like', $prefix . '-%')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        if (!$last) return $prefix . '-001';

        if (preg_match('/^([A-Z]+)-(\d+)$/', $last, $m)) {
            $num = (int) $m[2] + 1;
            return $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
        }
        return $prefix . '-001';
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

