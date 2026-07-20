<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一斉通知本体で必要となる管理項目を notifications テーブルへ追加します。
 *
 * 既存の notifications を一斉通知本体として再利用し、同じ役割を持つ
 * notification_batches の新設を避けます。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('notification_master_id')
                ->nullable()
                ->after('notification_type_id')
                ->constrained('notification_masters')
                ->nullOnDelete()
                ->comment('通知マスタID');

            $table->unsignedBigInteger('updated_by_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->comment('最終更新者ユーザーID');

            $table->boolean('is_important')
                ->default(false)
                ->after('body')
                ->comment('重要通知フラグ');

            $table->boolean('show_login_modal')
                ->default(false)
                ->after('is_important')
                ->comment('ログイン時モーダル表示フラグ');

            $table->boolean('confirmation_required')
                ->default(false)
                ->after('show_login_modal')
                ->comment('確認操作必須フラグ');

            $table->foreignId('board_post_id')
                ->nullable()
                ->after('confirmation_required')
                ->constrained('board_posts')
                ->nullOnDelete()
                ->comment('リンク先掲示板ID');

            $table->text('link_url')
                ->nullable()
                ->after('board_post_id')
                ->comment('任意リンクURL');

            $table->timestamp('expires_at')
                ->nullable()
                ->after('sent_at')
                ->comment('ポータル表示終了日時');

            $table->index('notification_master_id');
            $table->index('updated_by_user_id');
            $table->index('board_post_id');
            $table->index(['notification_status', 'scheduled_at']);
            $table->index(['notification_status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['notification_master_id']);
            $table->dropForeign(['board_post_id']);

            $table->dropIndex(['notification_master_id']);
            $table->dropIndex(['updated_by_user_id']);
            $table->dropIndex(['board_post_id']);
            $table->dropIndex(['notification_status', 'scheduled_at']);
            $table->dropIndex(['notification_status', 'sent_at']);

            $table->dropColumn([
                'notification_master_id',
                'updated_by_user_id',
                'is_important',
                'show_login_modal',
                'confirmation_required',
                'board_post_id',
                'link_url',
                'expires_at',
            ]);
        });
    }
};
