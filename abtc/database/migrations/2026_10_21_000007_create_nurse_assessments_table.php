<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nurse_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('nurse_station_id')->constrained('nurse_stations')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->string('bite_site', 150)->nullable();
            $table->string('animal_status', 20)->nullable();
            $table->boolean('animal_vaccinated')->nullable();
            $table->string('blood_pressure', 20)->nullable();
            $table->decimal('temperature', 5, 1)->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->longText('assessment_observations')->nullable();
            $table->longText('nursing_remarks')->nullable();
            $table->string('routing_option', 32)->nullable();
            $table->timestamp('draft_saved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['nurse_id', 'status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nurse_assessments');
    }
};
