<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_steps')) {
            return;
        }

        Schema::create('learning_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained('learning_sessions')->cascadeOnDelete();
            $table->string('step_type', 50);
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['learning_session_id', 'sort_order']);
            $table->index('step_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_steps');
    }
};
