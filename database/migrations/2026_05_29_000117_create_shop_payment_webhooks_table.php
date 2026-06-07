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
        Schema::create('shop_payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_payment_id')->nullable();
            $table->string('payment_provider');
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('provider_event_type')->nullable();
            $table->text('payload');
            $table->string('status')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_payment_webhooks');
    }
};
