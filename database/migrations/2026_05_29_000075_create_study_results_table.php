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
        Schema::create('study_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('study_session_id');
            $table->integer('total_questions');
            $table->integer('correct_answers');
            $table->integer('incorrect_answers');
            $table->decimal('accuracy_rate', 10, 2);
            $table->integer('streak_correct_count');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_results');
    }
};
