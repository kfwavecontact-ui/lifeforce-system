<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('notification_master_id')
                ->nullable()
                ->constrained('notification_masters')
                ->nullOnDelete()
                ->comment('通知マスタID');

            $table->unsignedBigInteger('user_id')->nullable()->comment('通知先ユーザーID');

            $table->string('recipient_role', 50)->nullable()->comment('通知先ロール');
            $table->string('recipient_name', 100)->nullable()->comment('通知先名');

            $table->string('channel', 30)->comment('通知チャネル portal/email/line');

            $table->string('title', 255)->comment('通知タイトル');
            $table->text('body')->nullable()->comment('通知本文');

            $table->string('status', 30)->default('sent')->comment('送信状態 sent/failed/read');
            $table->timestamp('sent_at')->nullable()->comment('送信日時');
            $table->timestamp('read_at')->nullable()->comment('既読日時');

            $table->string('related_type', 100)->nullable()->comment('関連種別');
            $table->unsignedBigInteger('related_id')->nullable()->comment('関連ID');

            $table->text('error_message')->nullable()->comment('エラー内容');

            $table->timestamps();

            $table->index('notification_master_id');
            $table->index('user_id');
            $table->index('recipient_role');
            $table->index('channel');
            $table->index('status');
            $table->index('sent_at');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_histories');
    }
};