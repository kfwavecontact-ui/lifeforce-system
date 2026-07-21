<?php

use App\Enums\BadgeGrantMethod;
use App\Enums\StudentBadgeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 生徒バッジへ現在状態、付与・取り外し情報、通知状態を追加する。
 *
 * 関連画面: わくわく > バッジ > 獲得履歴
 * 利用テーブル: student_badges（更新）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_badges', function (Blueprint $table) {
            $table->string('status', 20)
                ->default(StudentBadgeStatus::ACTIVE->value)
                ->after('badge_id');
            $table->string('grant_method', 20)
                ->default(BadgeGrantMethod::MANUAL->value)
                ->after('status');
            $table->foreignId('granted_by')->nullable()->after('acquired_at')->constrained('users')->nullOnDelete();
            $table->text('grant_reason')->nullable()->after('granted_by');
            $table->timestamp('removed_at')->nullable()->after('grant_reason');
            $table->foreignId('removed_by')->nullable()->after('removed_at')->constrained('users')->nullOnDelete();
            $table->string('removal_reason_code', 50)->nullable()->after('removed_by');
            $table->text('removal_reason_detail')->nullable()->after('removal_reason_code');
            $table->foreignId('regrant_source_id')->nullable()->after('removal_reason_detail')->constrained('student_badges')->nullOnDelete();
            $table->boolean('grant_notification_sent')->default(false)->after('regrant_source_id');
            $table->boolean('removal_notification_sent')->default(false)->after('grant_notification_sent');

            $table->index(['student_id', 'status'], 'student_badges_student_status_index');
            $table->index(['badge_id', 'status'], 'student_badges_badge_status_index');
            $table->index('acquired_at', 'student_badges_acquired_at_index');
            $table->index('removed_at', 'student_badges_removed_at_index');
        });

        // PostgreSQL部分ユニークインデックス:
        // 同一生徒・同一バッジについて、付与中の行を常に1件までに制限する。
        DB::statement("CREATE UNIQUE INDEX student_badges_active_unique ON student_badges (student_id, badge_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS student_badges_active_unique');

        Schema::table('student_badges', function (Blueprint $table) {
            $table->dropIndex('student_badges_student_status_index');
            $table->dropIndex('student_badges_badge_status_index');
            $table->dropIndex('student_badges_acquired_at_index');
            $table->dropIndex('student_badges_removed_at_index');
            $table->dropConstrainedForeignId('regrant_source_id');
            $table->dropConstrainedForeignId('removed_by');
            $table->dropConstrainedForeignId('granted_by');
            $table->dropColumn([
                'status',
                'grant_method',
                'grant_reason',
                'removed_at',
                'removal_reason_code',
                'removal_reason_detail',
                'grant_notification_sent',
                'removal_notification_sent',
            ]);
        });
    }
};
