<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'dob', 'sex',
        'contact_number', 'address', 'barangay', 'patient_type',
        'vaccine_type', 'planned_dose_count',
    ];

    protected $casts = [
        'dob' => 'date',
    ];

    public function fullName(): string
    {
        return trim("{$this->first_name} ".($this->middle_name ? $this->middle_name.' ' : '').$this->last_name.($this->suffix ? ', '.$this->suffix : ''));
    }

    public function biteIncidents()
    {
        return $this->hasMany(BiteIncident::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function vaccinationSchedules()
    {
        return $this->hasMany(VaccinationSchedule::class);
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
    }

    public function latestTicket()
    {
        return $this->hasOne(Ticket::class)->latestOfMany();
    }

    public function isPriority(): bool
    {
        return $this->patient_type !== 'regular';
    }

    public function patientTypeLabel(): string
    {
        return match ($this->patient_type) {
            'pwd' => 'PWD',
            'senior' => 'Senior',
            'pregnant' => 'Pregnant',
            'emergency' => 'Emergency',
            default => 'Regular',
        };
    }

    public static function patientTypes(): array
    {
        return ['regular', 'pwd', 'senior', 'pregnant', 'emergency'];
    }

    public static function suffixes(): array
    {
        return ['', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
    }

    public static function barangays(): array
    {
        return [
            'Addition Hills', 'Barangka', 'Daang Bakal', 'Greenhills',
            'Hagdang-hagdang', 'Hulo', 'Mauway', 'New Era',
            'National Power Corporation', 'Orbonia', 'Plainview', 'Poblacion',
            'San Antonio', 'San Isidro', 'St. Theresa', 'Ugong',
            'V. Mapa', 'Wack-Wack',
        ];
    }
}
