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
        Schema::create('badge_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('badge_id')->nullable();
            $table->string('requirement_type');
            $table->string('requirement_value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_requirements');
    }
};
