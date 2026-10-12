<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('vaccine_type')->nullable()->after('patient_type');
            $table->unsignedTinyInteger('planned_dose_count')->default(4)->after('vaccine_type');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['vaccine_type', 'planned_dose_count']);
        });
    }
};
