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
        Schema::create('course_prices', function (Blueprint $table) {
            $table->id();

            // courses.id
            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            // 週2 / 週3 / フリー
            $table->string('plan_type');

            // 金額
            $table->integer('price');

            // おすすめ表示
            $table->boolean('is_recommended')
                ->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_prices');
    }
};
