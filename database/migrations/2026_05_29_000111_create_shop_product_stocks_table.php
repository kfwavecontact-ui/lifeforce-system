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
        Schema::create('shop_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_product_id');
            $table->integer('stock_quantity');
            $table->integer('reserved_quantity');
            $table->integer('alert_quantity');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_product_stocks');
    }
};
