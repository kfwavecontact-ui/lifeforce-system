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
        Schema::create('sequence_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key')->unique();
            $table->string('prefix');
            $table->integer('current_number');
            $table->integer('padding_length');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence_numbers');
    }
};
