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
        Schema::create('learning_plan_task_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learning_plan_task_id');
            $table->unsignedBigInteger('learning_material_id');
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_plan_task_materials');
    }
};
