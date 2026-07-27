<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('benchmark_target_id')->constrained()->cascadeOnDelete();
            $table->string('resolution')->nullable();
            $table->string('quality')->nullable();
            $table->unsignedInteger('fps')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->string('unit')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmarks');
    }
};
