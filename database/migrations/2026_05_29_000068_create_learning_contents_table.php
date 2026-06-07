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
        Schema::create('learning_contents', function (Blueprint $table) {
            $table->id();
            $table->string('category_code');
            $table->string('category_name');
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('default_completion_type_id');
            $table->integer('default_required_minutes');
            $table->integer('default_required_count');
            $table->decimal('default_required_accuracy', 10, 2);
            $table->integer('default_required_streak');
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
        Schema::dropIfExists('learning_contents');
    }
};
