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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('employment_type_id');
            $table->unsignedBigInteger('teacher_status_id');
            $table->string('teacher_code')->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('phone_number');
            $table->date('hire_date')->nullable();
            $table->date('resignation_date')->nullable();
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
        Schema::dropIfExists('teachers');
    }
};
