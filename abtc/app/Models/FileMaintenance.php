<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileMaintenance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'file_number', 'title', 'description', 'category', 'status',
        'file_path', 'file_type', 'file_size', 'created_by', 'updated_by',
        'effective_date', 'expiry_date', 'tags',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'file_size' => 'integer',
        'tags' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->lt(now());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'expired' => 'Expired',
            'archived' => 'Archived',
            'draft' => 'Draft',
            default => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'expired' => 'danger',
            'archived' => 'secondary',
            'draft' => 'warning',
            default => 'light',
        };
    }
}