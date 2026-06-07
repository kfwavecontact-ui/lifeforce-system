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
        Schema::create('reward_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reward_category_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('required_points');
            $table->integer('stock_quantity');
            $table->integer('stock_alert_quantity');
            $table->boolean('is_stock_managed');
            $table->string('image_url')->nullable();
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
        Schema::dropIfExists('reward_items');
    }
};
