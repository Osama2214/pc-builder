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
            $table->string('screen_size')->nullable();
            $table->string('resolution')->nullable();
            $table->string('refresh_rate')->nullable();
            $table->string('panel_type')->nullable();
            $table->string('response_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_specifications', function (Blueprint $table) {
            $table->dropColumn(['screen_size', 'resolution', 'refresh_rate', 'panel_type', 'response_time']);
        });
    }
};
