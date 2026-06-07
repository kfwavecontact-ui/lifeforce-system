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
        Schema::create('event_result_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_schedule_id');
            $table->unsignedBigInteger('event_application_id');
            $table->unsignedBigInteger('student_id');
            $table->string('result_type');
            $table->integer('rank');
            $table->integer('score');
            $table->integer('win_count');
            $table->integer('loss_count');
            $table->boolean('is_cleared');
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_result_records');
    }
};
