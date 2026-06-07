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
        Schema::create('student_lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_lesson_reservation_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('lesson_session_id');
            $table->unsignedBigInteger('note_type_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->boolean('is_private');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_lesson_notes');
    }
};
