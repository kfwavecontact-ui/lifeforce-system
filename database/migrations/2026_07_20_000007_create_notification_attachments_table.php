<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一斉通知の複数添付ファイル情報を保存します。
 *
 * 実ファイルは BoardMediaService と同じ Storage 経由の方式を利用し、
 * storage_key には保存先キーだけを保持します。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete();
            $table->string('original_name')->comment('元ファイル名');
            $table->text('storage_key')->comment('保存先キー');
            $table->string('mime_type', 150)->nullable()->comment('MIMEタイプ');
            $table->unsignedBigInteger('size_bytes')->default(0)->comment('ファイル容量');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順');
            $table->timestamps();

            $table->index(['notification_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attachments');
    }
};
