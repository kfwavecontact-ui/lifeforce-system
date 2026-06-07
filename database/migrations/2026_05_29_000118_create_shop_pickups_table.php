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
        Schema::create('shop_pickups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_order_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pickup_school_id')->nullable();
            $table->string('pickup_status')->nullable();
            $table->date('pickup_scheduled_date');
            $table->timestamp('picked_up_at')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_pickups');
    }
};
