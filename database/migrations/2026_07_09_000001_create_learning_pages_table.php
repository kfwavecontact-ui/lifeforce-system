<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_pages')) {
            return;
        }

        Schema::create('learning_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_content_id')->unique()->constrained('routine_contents')->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->integer('time_limit_seconds')->nullable();
            $table->integer('attempt_limit')->nullable();
            $table->boolean('is_random')->default(false);
            $table->boolean('allow_resume')->default(true);
            $table->boolean('bgm_enabled')->default(false);
            $table->boolean('sound_enabled')->default(true);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_pages');
    }
};
