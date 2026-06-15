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
        Schema::create('challenge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_category_id')->constrained('challenge_categories');
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable();
            $table->integer('passing_score')->nullable();
            $table->integer('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('challenge_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges');
            $table->foreignId('badge_id')->nullable()->constrained('badges');
            $table->foreignId('title_id')->nullable()->constrained('titles');
            $table->integer('point_amount')->default(0);
            $table->timestamps();
        });

        Schema::create('challenge_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('challenge_id')->constrained('challenges');
            $table->dateTime('reserved_at');
            $table->string('reservation_status')->default('reserved');
            $table->foreignId('teacher_id')->nullable()->constrained('teachers');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('challenge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('challenge_id')->constrained('challenges');
            $table->foreignId('challenge_reservation_id')->nullable()->constrained('challenge_reservations');
            $table->foreignId('teacher_id')->nullable()->constrained('teachers');
            $table->integer('score')->nullable();
            $table->integer('passing_score')->nullable();
            $table->string('result');
            $table->text('comment')->nullable();
            $table->dateTime('challenged_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_logs');
        Schema::dropIfExists('challenge_reservations');
        Schema::dropIfExists('challenge_rewards');
        Schema::dropIfExists('challenges');
        Schema::dropIfExists('challenge_categories');
    }
};
