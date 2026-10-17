<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Ticket;

class QueueTicketNumber
{
    public static function next(bool $priority): string
    {
        $prefix = $priority ? 'P' : 'A';
        $last = Ticket::where('ticket_number', 'like', $prefix.'-%')
            ->orderByDesc('id')->value('ticket_number');
        $number = preg_match('/^[A-Z]+-(\d+)$/', (string) $last, $matches)
            ? (int) $matches[1] + 1
            : 1;

        return $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public static function forNewPatient(Patient $patient, bool $requiresMedicalReview = false): Ticket
    {
        return Ticket::create([
            'patient_id' => $patient->id,
            'ticket_number' => self::next($patient->isPriority()),
            'status' => 'waiting',
            'is_priority' => $patient->isPriority(),
            'visit_type' => 'new',
            'service_stage' => $requiresMedicalReview || $patient->patient_type === 'emergency'
                ? 'medical_review'
                : 'assessment',
        ]);
    }

    public static function forReturningPatient(Patient $patient, \App\Models\VaccinationSchedule $schedule): Ticket
    {
        return Ticket::create([
            'patient_id' => $patient->id,
            'vaccination_schedule_id' => $schedule->id,
            'ticket_number' => self::next($patient->isPriority()),
            'status' => 'waiting',
            'is_priority' => $patient->isPriority(),
            'visit_type' => 'returning',
            'service_stage' => $patient->patient_type === 'emergency' ? 'medical_review' : 'follow_up',
        ]);
    }
}
