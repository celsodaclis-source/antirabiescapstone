<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccination_schedules', function (Blueprint $table) {
            $table->string('administration_route', 100)->nullable()->after('inventory_id');
            $table->string('administration_site', 100)->nullable()->after('administration_route');
            $table->text('administration_remarks')->nullable()->after('administration_site');
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_schedules', function (Blueprint $table) {
            $table->dropColumn(['administration_route', 'administration_site', 'administration_remarks']);
        });
    }
};
