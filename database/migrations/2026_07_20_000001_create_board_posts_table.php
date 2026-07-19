<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_posts', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->default('教室からのお知らせ');
            $table->string('title', 255);
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->dateTime('publish_from')->nullable();
            $table->dateTime('publish_to')->nullable();
            $table->boolean('is_important')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->string('attachment_name')->nullable();
            $table->text('attachment_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'publish_from', 'publish_to']);
            $table->index(['is_pinned', 'is_important']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_posts');
    }
};
