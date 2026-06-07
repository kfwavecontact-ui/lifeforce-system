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
        Schema::create('event_attendance_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_schedule_id');
            $table->unsignedBigInteger('event_participant_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('event_reward_id');
            $table->string('reward_type');
            $table->integer('points');
            $table->unsignedBigInteger('badge_id')->nullable();
            $table->unsignedBigInteger('title_id')->nullable();
            $table->unsignedBigInteger('granted_by');
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_attendance_rewards');
    }
};
