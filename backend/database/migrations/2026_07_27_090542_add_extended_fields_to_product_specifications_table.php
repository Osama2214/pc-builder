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
        Schema::table('product_specifications', function (Blueprint $table) {
            // CPU
            $table->string('boost_clock')->nullable();
            $table->string('architecture')->nullable();
            $table->string('integrated_graphics')->nullable();
            $table->string('cache_size')->nullable();

            // Motherboard
            $table->unsignedInteger('m2_slots')->nullable();
            $table->unsignedInteger('sata_ports')->nullable();
            $table->string('wifi')->nullable();

            // GPU
            $table->unsignedInteger('length_mm')->nullable();
            $table->string('video_ports')->nullable();

            // RAM
            $table->string('ram_speed')->nullable();
            $table->string('cas_latency')->nullable();
            $table->string('kit_config')->nullable();

            // Storage / Motherboard
            $table->string('storage_interface')->nullable();
            $table->string('storage_type')->nullable();
            $table->unsignedInteger('read_speed')->nullable();
            $table->unsignedInteger('write_speed')->nullable();

            // PSU
            $table->string('modular_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_specifications', function (Blueprint $table) {
            $table->dropColumn([
                'boost_clock', 'architecture', 'integrated_graphics', 'cache_size',
                'm2_slots', 'sata_ports', 'wifi',
                'length_mm', 'video_ports',
                'ram_speed', 'cas_latency', 'kit_config',
                'storage_interface', 'storage_type', 'read_speed', 'write_speed',
                'modular_type',
            ]);
        });
    }
};
