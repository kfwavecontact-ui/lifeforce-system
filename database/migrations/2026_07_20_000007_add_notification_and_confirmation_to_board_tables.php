<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 教室からのご連絡に通知予約・通知実績・確認必須を追加し、
     * 既読履歴へ確認日時を追加します。
     */
    public function up(): void
    {
        Schema::table('board_posts', function (Blueprint $table) {
            $table->dateTime('notify_at')->nullable()->after('is_notified')->index();
            $table->dateTime('notified_at')->nullable()->after('notify_at')->index();
            $table->boolean('requires_confirmation')->default(false)->after('notified_at')->index();
        });

        Schema::table('board_reads', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('read_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('board_reads', function (Blueprint $table) {
            $table->dropIndex(['confirmed_at']);
            $table->dropColumn('confirmed_at');
        });

        Schema::table('board_posts', function (Blueprint $table) {
            $table->dropIndex(['notify_at']);
            $table->dropIndex(['notified_at']);
            $table->dropIndex(['requires_confirmation']);
            $table->dropColumn(['notify_at', 'notified_at', 'requires_confirmation']);
        });
    }
};
