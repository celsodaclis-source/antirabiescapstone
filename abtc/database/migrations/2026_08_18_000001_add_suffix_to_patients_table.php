<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('patients', 'suffix')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->string('suffix', 10)->nullable()->after('last_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'suffix')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('suffix');
            });
        }
    }
};
