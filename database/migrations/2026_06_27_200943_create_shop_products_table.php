<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')
                ->nullable()
                ->constrained('schools')
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->constrained('shop_product_categories')
                ->restrictOnDelete();

            $table->string('product_code', 30)->unique();

            $table->string('name');
            $table->text('description')->nullable();

            $table->integer('price');
            $table->integer('purchase_price')->default(0);

            $table->unsignedTinyInteger('tax_rate')->default(10);

            $table->integer('stock_quantity')->default(0);

            $table->boolean('is_stock_managed')->default(true);

            $table->integer('point_reward')->default(0);
            $table->integer('point_price')->default(0);

            $table->string('barcode', 100)->nullable();

            $table->string('image_path')->nullable();

            $table->boolean('is_online')->default(true);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();

            $table->integer('display_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index('display_order');
            $table->index('barcode');
            $table->index('published_at');
            $table->index('sales_end_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};