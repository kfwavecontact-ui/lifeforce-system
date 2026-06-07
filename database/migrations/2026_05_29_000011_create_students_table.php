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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('grade_id');
            $table->unsignedBigInteger('enrollment_status_id');
            $table->string('student_code')->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->date('enrolled_at');
            $table->date('withdrawn_at')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
