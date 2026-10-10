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
        // Fix: add patient_type column if missing (SQLite-safe)
        if (!Schema::hasColumn('patients', 'patient_type')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->enum('patient_type', ['regular', 'pwd', 'senior', 'pregnant', 'emergency'])
                    ->default('regular')
                    ->after('sex');
                $table->index('patient_type');
            });
        }

        // Fix: add is_priority column if missing on tickets
        if (!Schema::hasColumn('tickets', 'is_priority')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->boolean('is_priority')->default(false)->after('status');
                $table->index('is_priority');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop columns only if they exist (reverse of above)
        if (Schema::hasColumn('patients', 'patient_type')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropIndex(['patient_type']);
                $table->dropColumn('patient_type');
            });
        }

        if (Schema::hasColumn('tickets', 'is_priority')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropIndex(['is_priority']);
                $table->dropColumn('is_priority');
            });
        }
    }
};
 