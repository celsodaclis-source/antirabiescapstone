<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaccineInventory extends Model
{
    use HasFactory;

    protected $table = 'vaccine_inventory';

    protected $fillable = [
        'batch_number', 'vaccine_name', 'quantity', 'expiration_date',
        'received_date', 'low_stock_threshold',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'received_date' => 'date',
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    public function isExpiringSoon(int $warningDays = 30): bool
    {
        return $this->expiration_date->lte(now()->addDays($warningDays))
            && $this->expiration_date->gte(now());
    }

    public function isExpired(): bool
    {
        return $this->expiration_date->lt(now());
    }
}
