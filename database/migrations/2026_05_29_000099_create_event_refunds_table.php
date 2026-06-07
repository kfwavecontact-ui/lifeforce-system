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
        Schema::create('event_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_payment_id')->nullable();
            $table->string('payment_provider');
            $table->string('provider_refund_id')->nullable();
            $table->integer('refund_amount')->nullable();
            $table->string('refund_status')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_refunds');
    }
};
