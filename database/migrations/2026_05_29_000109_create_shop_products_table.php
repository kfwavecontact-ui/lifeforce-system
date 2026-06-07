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
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_category_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('product_type');
            $table->integer('price');
            $table->decimal('tax_rate', 10, 2);
            $table->boolean('is_stock_managed');
            $table->boolean('is_active');
            $table->integer('display_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};
