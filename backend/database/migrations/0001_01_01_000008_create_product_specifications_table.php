<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // CPU / Motherboard
            $table->string('socket')->nullable();
            $table->string('chipset')->nullable();
            $table->string('form_factor')->nullable();
            $table->unsignedInteger('cores')->nullable();
            $table->unsignedInteger('threads')->nullable();
            $table->string('clock_speed')->nullable();
            $table->string('cpu_generation')->nullable();

            // GPU / Motherboard (PCIe)
            $table->string('pcie_version')->nullable();
            $table->unsignedInteger('pcie_slots')->nullable();

            // RAM
            $table->string('ram_type')->nullable();
            $table->string('memory_type')->nullable();
            $table->string('memory_size')->nullable();
            $table->string('max_memory')->nullable();
            $table->unsignedInteger('memory_slots')->nullable();

            // Storage
            $table->string('capacity')->nullable();

            // PSU
            $table->unsignedInteger('power_draw')->nullable();
            $table->unsignedInteger('wattage')->nullable();
            $table->string('efficiency_rating')->nullable();

            // Case / GPU clearance
            $table->unsignedInteger('max_gpu_length')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
