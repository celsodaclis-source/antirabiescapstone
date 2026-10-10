<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function log(string $action, ?string $description = null, ?Model $auditable = null): AuditLog
    {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->getKey() : null,
            'created_at' => now(),
        ]);
    }

    public static function for(User $user, string $action, ?string $description = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
