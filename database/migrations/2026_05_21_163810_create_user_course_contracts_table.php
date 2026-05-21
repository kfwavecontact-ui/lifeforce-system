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
        Schema::create('user_course_contracts', function (Blueprint $table) {
            $table->id();

            // users.id
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // course_prices.id
            $table->foreignId('course_price_id')
                ->constrained()
                ->cascadeOnDelete();

            // 契約開始日
            $table->date('start_date');

            // 解約日（契約中はNULL）
            $table->date('end_date')
                ->nullable();

            // active / paused / canceled
            $table->string('status')
                ->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_course_contracts');
    }
};
