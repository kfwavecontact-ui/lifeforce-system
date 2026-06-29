<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_order_id')
                ->constrained('shop_orders')
                ->cascadeOnDelete();

            $table->foreignId('shop_product_id')
                ->nullable()
                ->constrained('shop_products')
                ->nullOnDelete();

            $table->string('product_name');
            $table->string('sku', 100)->nullable();

            $table->integer('quantity')->default(1);

            $table->integer('unit_price')->default(0);
            $table->integer('purchase_price')->default(0);

            $table->integer('discount_amount')->default(0);

            $table->unsignedTinyInteger('tax_rate')->default(10);
            $table->integer('tax_amount')->default(0);

            $table->integer('subtotal_amount')->default(0);
            $table->integer('total_amount')->default(0);

            $table->integer('point_reward')->default(0);
            $table->integer('point_used')->default(0);

            $table->text('memo')->nullable();

            $table->timestamps();

            $table->index('shop_order_id');
            $table->index('shop_product_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
    }
};