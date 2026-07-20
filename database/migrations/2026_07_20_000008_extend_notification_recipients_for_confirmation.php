<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受信者単位の確認状況と受信者区分を notification_recipients へ追加します。
 *
 * 既読情報は既存 read_at を使用し、確認必須通知の操作結果だけを
 * confirmed_at で分離管理します。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->string('recipient_type', 20)
                ->nullable()
                ->after('user_id')
                ->comment('受信者区分 student/parent');

            $table->timestamp('confirmed_at')
                ->nullable()
                ->after('read_at')
                ->comment('確認日時');

            $table->timestamp('delivered_at')
                ->nullable()
                ->after('delivery_status')
                ->comment('配信完了日時');

            $table->text('error_message')
                ->nullable()
                ->after('confirmed_at')
                ->comment('配信エラー内容');

            $table->index(['notification_id', 'user_id']);
            $table->index(['notification_id', 'recipient_type']);
            $table->index(['delivery_status', 'delivered_at']);
            $table->index('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->dropIndex(['notification_id', 'user_id']);
            $table->dropIndex(['notification_id', 'recipient_type']);
            $table->dropIndex(['delivery_status', 'delivered_at']);
            $table->dropIndex(['confirmed_at']);

            $table->dropColumn([
                'recipient_type',
                'confirmed_at',
                'delivered_at',
                'error_message',
            ]);
        });
    }
};
