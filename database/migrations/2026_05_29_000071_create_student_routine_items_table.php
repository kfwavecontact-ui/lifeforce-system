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
        Schema::create('student_routine_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_routine_id');
            $table->unsignedBigInteger('learning_content_id');
            $table->unsignedBigInteger('completion_type_id');
            $table->integer('required_minutes');
            $table->integer('required_count');
            $table->decimal('required_accuracy', 10, 2);
            $table->integer('required_streak');
            $table->integer('point_amount');
            $table->integer('sort_order');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_routine_items');
    }
};
