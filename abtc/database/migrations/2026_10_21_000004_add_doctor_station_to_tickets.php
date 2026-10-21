<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('doctor_station_id')->nullable()->after('assessment_completed_at')
                ->constrained('doctor_stations')->nullOnDelete();
            $table->index(['doctor_station_id', 'status'], 'tickets_doctor_station_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_doctor_station_status_idx');
            $table->dropConstrainedForeignId('doctor_station_id');
        });
    }
};
