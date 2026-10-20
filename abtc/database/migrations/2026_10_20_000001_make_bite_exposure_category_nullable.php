<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bite_incidents', function (Blueprint $table) {
            $table->enum('exposure_category', ['I', 'II', 'III'])->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('bite_incidents')->whereNull('exposure_category')->exists()) {
            throw new RuntimeException('Cannot make bite incident exposure categories required while blank categories exist.');
        }

        Schema::table('bite_incidents', function (Blueprint $table) {
            $table->enum('exposure_category', ['I', 'II', 'III'])->nullable(false)->change();
        });
    }
};
