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
        Schema::create('shop_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_order_id');
            $table->unsignedBigInteger('user_id');
            $table->string('payment_provider');
            $table->string('payment_method');
            $table->string('provider_checkout_id')->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->integer('amount');
            $table->string('currency');
            $table->string('status')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_payments');
    }
};
