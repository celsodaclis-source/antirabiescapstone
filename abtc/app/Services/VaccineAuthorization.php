<?php

namespace App\Services;

use App\Models\NurseStation;
use App\Models\Patient;
use App\Models\Ticket;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;

/**
 * Shared clinical-order checks used by the nurse station and the nurse
 * assessment routing workflow. These methods never mutate inventory.
 */
class VaccineAuthorization
{
    /** Why the patient's doctor-ordered plan cannot authorize administration, or null when valid. */
    public static function planError(?Patient $patient, ?VaccinationSchedule $schedule): ?string
    {
        if (! $patient || ! $schedule || $schedule->patient_id !== $patient->id) {
            return 'No vaccination dose is linked to this patient ticket.';
        }
        if (! $patient->vaccine_type || self::isImmunoglobulin($patient->vaccine_type)) {
            return 'A valid doctor-ordered vaccine plan is required before administration.';
        }
        $doseNumbers = $patient->vaccinationSchedules->pluck('dose_number');
        if (! in_array((int) $patient->planned_dose_count, [3, 4], true)
            || $doseNumbers->count() !== (int) $patient->planned_dose_count
            || $doseNumbers->unique()->count() !== (int) $patient->planned_dose_count
            || ! $doseNumbers->contains($schedule->dose_number)
            || $schedule->dose_number > (int) $patient->planned_dose_count
            || ! $patient->biteIncidents->contains(fn ($incident) => filled($incident->exposure_category))) {
            return 'The doctor must complete the exposure category and provider-ordered vaccine schedule before administration.';
        }
        if (! in_array($schedule->status, ['scheduled', 'missed'], true)) {
            return 'This dose is already administered or is not available.';
        }
        if ($schedule->scheduled_date->gt(today())) {
            return 'This dose is not due yet.';
        }
        if (VaccinationSchedule::where('patient_id', $patient->id)->where('dose_number', '<', $schedule->dose_number)
            ->where('status', '!=', 'administered')->exists()) {
            return 'Earlier doses must be administered first.';
        }

        return null;
    }

    /** Full reason the vaccine administration form is unavailable for this ticket, or null when usable. */
    public static function administrationBlockReason(Ticket $ticket): ?string
    {
        $patient = $ticket->patient;
        $schedule = $ticket->vaccinationSchedule;
        if ($reason = self::planError($patient, $schedule)) {
            return $reason;
        }
        if (! config('vaccination.administration_sites', [])) {
            return 'Clinic-approved administration sites are not configured.';
        }
        $hasBatch = VaccineInventory::where('vaccine_name', $patient->vaccine_type)
            ->where('quantity', '>', 0)->whereDate('expiration_date', '>=', today())->exists();

        return $hasBatch ? null : 'No matching unexpired vaccine batch has stock available.';
    }

    /**
     * The next due dose for a patient that has no un-administered earlier dose,
     * used when a nurse assessment routes a patient to vaccine administration.
     */
    public static function dueDose(Patient $patient): ?VaccinationSchedule
    {
        return $patient->vaccinationSchedules()
            ->whereIn('status', ['scheduled', 'missed'])
            ->whereDate('scheduled_date', '<=', today())
            ->whereRaw("not exists (select 1 from vaccination_schedules earlier_dose where earlier_dose.patient_id = vaccination_schedules.patient_id and earlier_dose.dose_number < vaccination_schedules.dose_number and earlier_dose.status != 'administered')")
            ->orderBy('dose_number')->first();
    }

    public static function isImmunoglobulin(string $productName): bool
    {
        return (bool) preg_match('/\\b(?:equi[\\s-]?rab|(?:human|equine)\\s+rabies\\s+(?:immunoglobulin|serum)|anti[- ]rabies\\s+serum|rabies\\s+(?:immunoglobulin|immune\\s+globulin)|(?:hrig|erig|rig))\\b/i', $productName);
    }

    /** The nurse station currently occupied by the given nurse, if any. */
    public static function stationFor(int $nurseId): ?NurseStation
    {
        return NurseStation::where('user_id', $nurseId)->first();
    }
}
