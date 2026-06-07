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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('name');
            $table->string('short_name');
            $table->string('kana_name');
            $table->string('postal_code');
            $table->string('prefecture');
            $table->string('city');
            $table->string('address1');
            $table->string('address2')->nullable();
            $table->string('phone_number');
            $table->string('email');
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->integer('capacity');
            $table->integer('sort_order');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
