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
        Schema::create('lesson_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_schedule_id');
            $table->unsignedBigInteger('calendar_event_id');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('lesson_type_id');
            $table->unsignedBigInteger('teacher_user_id');
            $table->string('title');
            $table->date('lesson_date');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('lesson_status');
            $table->integer('max_students');
            $table->text('note')->nullable();
            $table->boolean('is_cancelled')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_sessions');
    }
};
