<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_inventory', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->string('vaccine_name');
            $table->integer('quantity')->default(0);
            $table->date('expiration_date');
            $table->date('received_date')->nullable();
            $table->integer('low_stock_threshold')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccine_inventory');
    }
};
