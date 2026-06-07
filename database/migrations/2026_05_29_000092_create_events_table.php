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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_category_id');
            $table->unsignedBigInteger('event_status_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('organizer_type');
            $table->unsignedBigInteger('organizer_user_id');
            $table->boolean('is_paid');
            $table->boolean('is_external_allowed')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
