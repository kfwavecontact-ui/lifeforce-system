<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_post_id')->constrained('board_posts')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->dateTime('read_at');
            $table->timestamps();

            $table->unique(['board_post_id', 'user_id']);
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_reads');
    }
};
