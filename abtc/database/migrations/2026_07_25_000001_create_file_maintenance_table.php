<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->string('status')->default('active');
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_maintenances');
    }
};