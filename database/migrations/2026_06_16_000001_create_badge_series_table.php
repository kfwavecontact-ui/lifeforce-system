<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('badge_category_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('badge_category_id');
            $table->index('display_order');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_series');
    }
};