<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiteIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'date_of_bite', 'animal_type', 'exposure_category',
        'bite_site', 'animal_status', 'animal_vaccinated',
    ];

    protected $casts = [
        'date_of_bite' => 'date',
        'animal_vaccinated' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
