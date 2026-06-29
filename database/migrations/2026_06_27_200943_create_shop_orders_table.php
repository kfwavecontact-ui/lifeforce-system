<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            $table->string('order_no', 50)->unique();

            $table->string('order_status', 30)->default('ordered');
            $table->string('payment_status', 30)->default('unpaid');

            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->nullOnDelete();

            $table->timestamp('ordered_at')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->date('transaction_date')->nullable();

            $table->integer('subtotal_amount')->default(0);
            $table->integer('discount_amount')->default(0);
            $table->integer('coupon_discount')->default(0);
            $table->integer('point_discount')->default(0);
            $table->integer('campaign_discount')->default(0);
            $table->integer('shipping_fee')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total_amount')->default(0);

            $table->string('buyer_name', 100)->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone', 30)->nullable();

            $table->string('delivery_method', 30)->nullable();
            $table->string('delivery_status', 30)->default('not_required');
            $table->timestamp('delivered_at')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->text('memo')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['order_status', 'payment_status']);
            $table->index('payment_method_id');
            $table->index('ordered_at');
            $table->index('scheduled_date');
            $table->index('transaction_date');
            $table->index('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};