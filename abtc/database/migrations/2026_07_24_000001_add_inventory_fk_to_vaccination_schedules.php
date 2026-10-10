<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vaccination_schedules', function (Blueprint $table) {
            $table->foreign('inventory_id')
                  ->references('id')
                  ->on('vaccine_inventory')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_schedules', function (Blueprint $table) {
            $table->dropForeign(['inventory_id']);
        });
    }
};
