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
        Schema::create('learning_routines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('routine_type_id');
            $table->unsignedBigInteger('manager_user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency');
            $table->integer('target_count');
            $table->string('target_unit');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('show_on_dashboard');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_routines');
    }
};
