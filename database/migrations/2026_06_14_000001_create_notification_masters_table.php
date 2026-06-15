<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_masters', function (Blueprint $table) {
            $table->id();

            $table->string('code', 100)->unique()->comment('通知コード');
            $table->string('name', 100)->comment('通知名');
            $table->string('category', 50)->comment('カテゴリ');
            $table->text('description')->nullable()->comment('説明');

            $table->boolean('default_enabled')->default(true)->comment('初期ON/OFF');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');

            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_masters');
    }
};