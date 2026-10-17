<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('visit_type', 20)->default('new')->after('status');
            $table->string('service_stage', 30)->default('assessment')->after('visit_type');
            $table->foreignId('vaccination_schedule_id')->nullable()->after('patient_id')
                ->constrained('vaccination_schedules')->nullOnDelete();
            $table->index(['service_stage', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['service_stage', 'status']);
            $table->dropConstrainedForeignId('vaccination_schedule_id');
            $table->dropColumn(['visit_type', 'service_stage']);
        });
    }
};
