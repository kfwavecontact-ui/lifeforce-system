<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_completion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('student_routine_id');
            $table->unsignedBigInteger('student_routine_item_id');

            $table->date('completed_on');
            $table->integer('actual_minutes')->default(0);
            $table->integer('actual_count')->default(0);
            $table->integer('actual_accuracy')->default(0);
            $table->integer('point_amount')->default(0);

            $table->boolean('is_completed')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_completion_logs');
    }
};