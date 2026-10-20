<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Administrative Staff had the Nurse access tier. Preserve those accounts
        // and their access while removing the retired role value.
        DB::table('users')
            ->where('role', 'Administrative Staff')
            ->update(['role' => 'Nurse']);
    }

    public function down(): void
    {
        // This data conversion is intentionally irreversible: the Nurse role is
        // valid, and reverting would incorrectly relabel all Nurse accounts.
    }
};
