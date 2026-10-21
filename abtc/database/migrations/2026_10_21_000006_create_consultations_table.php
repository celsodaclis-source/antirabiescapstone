<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('doctor_station_id')->constrained('doctor_stations')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->longText('clinical_narrative')->nullable();
            $table->string('exposure_category', 10)->nullable();
            $table->string('treatment_decision', 120)->nullable();
            $table->string('vaccine_type')->nullable();
            $table->string('regimen_route', 100)->nullable();
            $table->string('schedule_code', 32)->nullable();
            $table->text('vaccination_schedule')->nullable();
            $table->unsignedTinyInteger('planned_dose_count')->nullable();
            $table->text('additional_instructions')->nullable();
            $table->string('disposition', 32)->nullable();
            $table->text('disposition_remarks')->nullable();
            $table->string('referral_destination')->nullable();
            $table->timestamp('draft_saved_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->index(['doctor_id', 'status', 'finalized_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
