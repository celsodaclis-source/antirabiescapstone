<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NurseAssessment extends Model
{
    public const ROUTING_OPTIONS = ['send_to_doctor', 'vaccine_administration', 'on_hold'];
    public const STATUSES = ['draft', 'completed', 'on_hold'];

    protected $fillable = [
        'ticket_id', 'patient_id', 'nurse_id', 'nurse_station_id', 'status',
        'bite_site', 'animal_status', 'animal_vaccinated',
        'blood_pressure', 'temperature', 'pulse_rate', 'respiratory_rate', 'weight',
        'assessment_observations', 'nursing_remarks', 'routing_option',
        'draft_saved_at', 'completed_at',
    ];

    protected $casts = [
        'animal_vaccinated' => 'boolean',
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'draft_saved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function nurseStation()
    {
        return $this->belongsTo(NurseStation::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            default => 'Draft',
        };
    }

    public function routingOptionLabel(): string
    {
        return match ($this->routing_option) {
            'send_to_doctor' => 'Send to Doctor — For consultation or reassessment',
            'vaccine_administration' => 'Proceed to Vaccine Administration — Under an authorized plan',
            'on_hold' => 'On Hold — Awaiting further assessment',
            default => 'Not selected',
        };
    }

    /** Readable vital-signs summary used by review screens and the copy-draft action. */
    public function vitalSignsSummary(): string
    {
        $parts = [];
        if (filled($this->blood_pressure)) $parts[] = 'BP '.$this->blood_pressure.' mmHg';
        if (filled($this->temperature)) $parts[] = 'Temp '.$this->temperature.' °C';
        if (filled($this->pulse_rate)) $parts[] = 'Pulse '.$this->pulse_rate.' bpm';
        if (filled($this->respiratory_rate)) $parts[] = 'RR '.$this->respiratory_rate.' breaths/min';
        if (filled($this->weight)) $parts[] = 'Weight '.$this->weight.' kg';

        return $parts === [] ? 'Not recorded' : implode('; ', $parts);
    }
}
