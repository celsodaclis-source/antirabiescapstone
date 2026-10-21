<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NurseStation extends Model
{
    protected $fillable = ['name', 'user_id'];

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
