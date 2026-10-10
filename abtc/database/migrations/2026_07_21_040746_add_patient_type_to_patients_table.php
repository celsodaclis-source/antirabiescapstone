<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->enum('patient_type', ['regular', 'pwd', 'senior', 'pregnant', 'emergency'])
                ->default('regular')
                ->after('sex');
            $table->index('patient_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['patient_type']);
            $table->dropColumn('patient_type');
        });
    }
};

