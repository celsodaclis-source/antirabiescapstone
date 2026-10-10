<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    public const STATUSES = ['waiting', 'called', 'in_progress', 'held', 'completed', 'cancelled'];

    protected $fillable = [
        'patient_id', 'ticket_number', 'status', 'counter_assigned',
        'assigned_staff_id', 'called_at', 'served_at', 'completed_at',
        'is_priority',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'served_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_priority' => 'boolean',
    ];

    /**
     * Scope a query to order priority tickets first.
     */
    public function scopePriorityFirst($query)
    {
        return $query->orderByDesc('is_priority')->orderBy('created_at');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'waiting' => 'warning',
            'called', 'in_progress' => 'info',
            'held' => 'secondary',
            'cancelled' => 'danger',
            default => 'light',
        };
    }

    public function timeInQueue(): string
    {
        $start = $this->called_at ?? $this->created_at;
        $end = $this->completed_at ?? now();
        return $start->diffForHumans($end, true);
    }
}
