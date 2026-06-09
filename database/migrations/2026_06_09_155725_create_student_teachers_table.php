<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_teachers', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('student_id');

            $table->unsignedBigInteger('teacher_id');

            $table->boolean('is_primary')->default(true);

            $table->date('started_at')->nullable();

            $table->date('ended_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_teachers');
    }
};