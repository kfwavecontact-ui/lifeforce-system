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
        Schema::create('learning_routine_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learning_routine_id');
            $table->unsignedBigInteger('student_id');
            $table->date('target_date')->nullable();
            $table->boolean('is_completed');
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_routine_logs');
    }
};
