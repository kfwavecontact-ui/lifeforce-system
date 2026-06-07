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
        Schema::create('learning_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('learning_plan_type_id');
            $table->unsignedBigInteger('manager_user_id');
            $table->unsignedBigInteger('qualification_id')->nullable();
            $table->string('title');
            $table->string('goal');
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status');
            $table->integer('progress_rate');
            $table->text('note')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_plans');
    }
};
