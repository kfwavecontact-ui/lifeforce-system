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
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('badge_category_id');
            $table->string('name');
            $table->integer('level');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('locked_image_url')->nullable();
            $table->integer('point_reward');
            $table->boolean('is_limited');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('display_order');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
