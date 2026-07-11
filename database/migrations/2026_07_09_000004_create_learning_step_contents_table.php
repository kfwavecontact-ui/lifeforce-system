<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_step_contents')) {
            return;
        }

        Schema::create('learning_step_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_step_id')->unique()->constrained('learning_steps')->cascadeOnDelete();
            $table->string('content_title')->nullable();
            $table->text('body')->nullable();
            $table->string('media_type', 50)->nullable();
            $table->string('media_path')->nullable();
            $table->json('settings')->nullable();
            $table->json('questions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_step_contents');
    }
};
