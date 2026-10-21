<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    protected $fillable = [
        'ticket_id', 'patient_id', 'doctor_id', 'doctor_station_id', 'status',
        'clinical_narrative', 'exposure_category', 'vaccine_type',
        'schedule_code', 'vaccination_schedule', 'planned_dose_count',
        'additional_instructions', 'disposition', 'disposition_remarks', 'referral_destination',
        'draft_saved_at', 'finalized_at',
    ];

    protected $casts = ['draft_saved_at' => 'datetime', 'finalized_at' => 'datetime'];

    public function ticket() { return $this->belongsTo(Ticket::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctor() { return $this->belongsTo(User::class, 'doctor_id'); }
    public function doctorStation() { return $this->belongsTo(DoctorStation::class); }
}
