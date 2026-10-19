<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedTinyInteger('planned_dose_count')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('patients')->whereNull('planned_dose_count')->update(['planned_dose_count' => 4]);

        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedTinyInteger('planned_dose_count')->default(4)->nullable(false)->change();
        });
    }
};
