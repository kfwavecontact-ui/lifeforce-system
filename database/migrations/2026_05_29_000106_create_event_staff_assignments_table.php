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
        Schema::create('event_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_schedule_id');
            $table->unsignedBigInteger('user_id');
            $table->string('staff_role');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_staff_assignments');
    }
};
