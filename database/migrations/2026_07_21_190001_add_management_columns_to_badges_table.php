<?php

use App\Enums\BadgeGrantMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * バッジマスタへ付与運用・通知・監査用項目を追加する。
 *
 * 関連画面: わくわく > バッジ > バッジ一覧
 * 利用テーブル: badges（更新）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->string('grant_method', 20)
                ->default(BadgeGrantMethod::BOTH->value)
                ->after('code');
            $table->text('acquisition_message')->nullable()->after('description');
            $table->boolean('allow_regrant')->default(true)->after('acquisition_message');
            $table->boolean('notify_on_grant')->default(true)->after('allow_regrant');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index(['grant_method', 'is_active'], 'badges_grant_method_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropIndex('badges_grant_method_active_index');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'grant_method',
                'acquisition_message',
                'allow_regrant',
                'notify_on_grant',
            ]);
        });
    }
};
