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
        Schema::create('learning_plan_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learning_plan_id');
            $table->string('title');
            $table->date('target_date')->nullable();
            $table->string('status');
            $table->integer('progress_rate');
            $table->integer('sort_order');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_plan_milestones');
    }
};
