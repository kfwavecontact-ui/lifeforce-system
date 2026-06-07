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
        Schema::create('learning_plan_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learning_plan_id');
            $table->unsignedBigInteger('learning_plan_milestone_id');
            $table->unsignedBigInteger('learning_plan_task_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->integer('progress_rate');
            $table->text('comment');
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_plan_progress_logs');
    }
};
