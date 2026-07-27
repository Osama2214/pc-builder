<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('build_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('build_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('slot'); // cpu|motherboard|gpu|cooler|case|psu|ram|storage
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['build_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_items');
    }
};
