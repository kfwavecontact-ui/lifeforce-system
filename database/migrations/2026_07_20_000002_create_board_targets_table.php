<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_post_id')->constrained('board_posts')->cascadeOnDelete();
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->unique(['board_post_id', 'target_type', 'target_id'], 'board_targets_unique');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_targets');
    }
};
