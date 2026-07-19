<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_post_id')->constrained('board_posts')->cascadeOnDelete();
            $table->string('original_name');
            $table->text('storage_key');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['board_post_id', 'sort_order']);
        });

        // 旧単一添付データがある場合は、新しい複数添付テーブルへ引き継ぐ。
        DB::table('board_posts')
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->each(function ($post) {
                DB::table('board_attachments')->insert([
                    'board_post_id' => $post->id,
                    'original_name' => $post->attachment_name ?: basename($post->attachment_path),
                    'storage_key' => $post->attachment_path,
                    'mime_type' => null,
                    'size_bytes' => 0,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_attachments');
    }
};
