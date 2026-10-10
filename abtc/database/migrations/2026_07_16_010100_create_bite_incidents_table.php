<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bite_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->date('date_of_bite');
            $table->string('animal_type'); // Dog, Cat, Monkey, etc.
            $table->enum('exposure_category', ['I', 'II', 'III']);
            $table->string('bite_site')->nullable();
            $table->enum('animal_status', ['Healthy', 'Sick', 'Dead', 'Unknown'])->nullable();
            $table->boolean('animal_vaccinated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bite_incidents');
    }
};
