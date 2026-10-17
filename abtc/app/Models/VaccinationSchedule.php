<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaccinationSchedule extends Model
{
    use HasFactory;

    public const DOSE_DAYS = [0, 3, 7, 28];

    protected $fillable = [
        'patient_id', 'dose_number', 'scheduled_date', 'status',
        'administered_at', 'administered_by', 'inventory_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'administered_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function administeredBy()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function inventory()
    {
        return $this->belongsTo(VaccineInventory::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['scheduled', 'missed'], true)
            && $this->scheduled_date->lt(now()->startOfDay());
    }
}
