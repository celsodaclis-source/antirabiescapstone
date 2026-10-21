<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('assessment_completed_by')->nullable()->after('assigned_staff_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('assessment_completed_at')->nullable()->after('assessment_completed_by');
            $table->index(['status', 'service_stage', 'assessment_completed_at'], 'tickets_doctor_waiting_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_doctor_waiting_idx');
            $table->dropConstrainedForeignId('assessment_completed_by');
            $table->dropColumn('assessment_completed_at');
        });
    }
};
