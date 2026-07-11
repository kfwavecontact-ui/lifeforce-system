<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_sessions')) {
            return;
        }

        Schema::create('learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_page_id')->constrained('learning_pages')->cascadeOnDelete();
            $table->integer('session_no');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->boolean('show_subtitle')->default(false);
            $table->text('developer_note')->nullable();
            $table->string('development_status')->default('not_started');
            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['learning_page_id', 'session_no']);
            $table->index(['learning_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_sessions');
    }
};
