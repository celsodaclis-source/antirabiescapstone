<?php

namespace Database\Seeders;

use App\Models\BiteIncident;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AbtcDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261019);

        $stock = collect(['PVRV', 'PCECV'])->mapWithKeys(function (string $vaccine) {
            $batchNumber = 'DEMO-'.$vaccine.'-2026';
            $inventory = VaccineInventory::firstOrCreate(
                ['batch_number' => $batchNumber],
                [
                    'vaccine_name' => $vaccine,
                    'quantity' => 5000,
                    'received_date' => today()->subDays(30),
                    'expiration_date' => today()->addYears(2),
                    'low_stock_threshold' => 10,
                ],
            );

            return [$vaccine => $inventory];
        });

        $created = 0;
        DB::transaction(function () use ($stock, &$created) {
            for ($number = 1; $number <= 100; $number++) {
                $marker = sprintf('DEMO SEED ONLY #%03d - ', $number);
                if (Patient::where('address', 'like', $marker.'%')->exists()) {
                    continue;
                }

                $type = $number % 17 === 0 ? 'emergency' : Patient::patientTypes()[($number - 1) % 4];
                $vaccine = $number % 2 === 0 ? 'PVRV' : 'PCECV';
                $plannedDoses = 4;
                $biteDate = today()->subDays(mt_rand(0, 45));
                $createdAt = now()->subDays(mt_rand(0, 29));

                $patient = Patient::create([
                    'first_name' => $this->randomItem(['Alex', 'Angela', 'Bea', 'Carlo', 'Daniel', 'Ella', 'Francis', 'Grace', 'Jamie', 'Jordan', 'Kai', 'Leah', 'Miguel', 'Nina', 'Paolo', 'Rosa', 'Sam', 'Tina', 'Victor', 'Zoe']),
                    'middle_name' => $number % 4 === 0 ? null : $this->randomItem(['Santos', 'Reyes', 'Cruz', 'Garcia', 'Ramos', 'Flores', 'Mendoza', 'Bautista']),
                    'last_name' => $this->randomItem(['Santos', 'Reyes', 'Cruz', 'Garcia', 'Ramos', 'Flores', 'Mendoza', 'Bautista', 'Rivera', 'Castillo', 'Torres', 'Aquino']),
                    'dob' => today()->subYears(mt_rand(1, 85))->subDays(mt_rand(0, 364))->toDateString(),
                    'sex' => $this->randomItem(['Male', 'Female', 'Other']),
                    'contact_number' => '0997'.str_pad((string) (1000000 + $number), 7, '0', STR_PAD_LEFT),
                    'address' => $marker.mt_rand(1, 250).' '.$this->randomItem(['Sample Street', 'Demo Avenue', 'Test Road', 'Example Lane']),
                    'barangay' => $this->randomItem(Patient::barangays()),
                    'patient_type' => $type,
                    'vaccine_type' => $vaccine,
                    'planned_dose_count' => $plannedDoses,
                ]);
                $patient->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

                BiteIncident::create([
                    'patient_id' => $patient->id,
                    'date_of_bite' => $biteDate,
                    'animal_type' => $this->randomItem(['Dog', 'Cat', 'Monkey']),
                    'exposure_category' => $this->randomItem(['II', 'II', 'III']),
                    'bite_site' => $this->randomItem(['Hand', 'Arm', 'Leg', 'Face']),
                    'animal_status' => $this->randomItem(['Healthy', 'Sick', 'Dead', 'Unknown']),
                    'animal_vaccinated' => mt_rand(1, 100) <= 35,
                ]);

                $progress = $number % 5;
                $scheduled = [];
                foreach ([0, 3, 7, 28] as $index => $daysAfterBite) {
                    $scheduledDate = $biteDate->copy()->addDays($daysAfterBite);
                    $status = 'scheduled';
                    if ($index < $progress && $scheduledDate->lte(today())) {
                        $status = 'administered';
                    } elseif ($scheduledDate->lt(today()) && $number % 4 === 0) {
                        $status = 'missed';
                    }

                    $schedule = VaccinationSchedule::create([
                        'patient_id' => $patient->id,
                        'dose_number' => $index + 1,
                        'scheduled_date' => $scheduledDate,
                        'status' => $status,
                        'administered_at' => $status === 'administered' ? $scheduledDate->copy()->setTime(9, 0) : null,
                        'administered_by' => null,
                        'inventory_id' => $status === 'administered' ? $stock[$vaccine]->id : null,
                    ]);
                    $scheduled[] = $schedule;

                    if ($status === 'administered') {
                        $stock[$vaccine]->decrement('quantity');
                    }
                }

                $followUpSchedule = collect($scheduled)->first(function (VaccinationSchedule $schedule) {
                    return in_array($schedule->status, ['scheduled', 'missed'], true)
                        && $schedule->scheduled_date->lte(today());
                });
                $isReturning = $number % 3 === 0 && $followUpSchedule !== null;
                $ticketNumber = $this->nextTicketNumber($patient->isPriority());
                $status = match (true) {
                    $number <= 45 => 'completed',
                    $number <= 75 => 'waiting',
                    $number <= 78 => 'held',
                    $number <= 80 => 'called',
                    $number === 81 => 'in_progress',
                    default => 'cancelled',
                };
                $counter = match ($number) {
                    79 => 1,
                    80 => 2,
                    81 => 3,
                    default => null,
                };
                $ticketCreatedAt = $createdAt->copy()->addMinutes(mt_rand(1, 180));

                Ticket::create([
                    'patient_id' => $patient->id,
                    'vaccination_schedule_id' => $isReturning ? $followUpSchedule->id : null,
                    'ticket_number' => $ticketNumber,
                    'status' => $status,
                    'is_priority' => $patient->isPriority(),
                    'visit_type' => $isReturning ? 'returning' : 'new',
                    'service_stage' => $isReturning
                        ? ($type === 'emergency' ? 'medical_review' : 'follow_up')
                        : ($type === 'emergency' ? 'medical_review' : 'assessment'),
                    'counter_assigned' => $counter,
                    'assigned_staff_id' => null,
                    'called_at' => in_array($status, ['called', 'in_progress', 'completed'], true) ? $ticketCreatedAt->copy()->addMinutes(10) : null,
                    'served_at' => in_array($status, ['in_progress', 'completed'], true) ? $ticketCreatedAt->copy()->addMinutes(15) : null,
                    'completed_at' => $status === 'completed' ? $ticketCreatedAt->copy()->addMinutes(30) : null,
                    'created_at' => $ticketCreatedAt,
                    'updated_at' => $ticketCreatedAt,
                ]);

                $created++;
            }
        });

        $this->command?->info("Added {$created} demo patients (100 maximum). Existing records were preserved.");
        $this->command?->line('Demo-only records are marked in the patient address; no existing records were deleted or reset.');
    }

    private function randomItem(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function nextTicketNumber(bool $priority): string
    {
        $prefix = $priority ? 'P' : 'A';
        $latest = Ticket::where('ticket_number', 'like', $prefix.'-%')->orderByDesc('id')->value('ticket_number');
        $number = preg_match('/^[A-Z]+-(\d+)$/', (string) $latest, $matches) ? ((int) $matches[1]) + 1 : 1;

        do {
            $ticketNumber = $prefix.'-'.str_pad((string) $number++, 3, '0', STR_PAD_LEFT);
        } while (Ticket::where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }
}
