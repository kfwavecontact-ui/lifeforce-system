<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 生徒バッジの付与・取り外し・再付与を時系列で保存する履歴テーブルを作成する。
 *
 * 関連画面: わくわく > バッジ > 獲得履歴 > 詳細
 * 利用テーブル: student_badge_events（新規作成）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_badge_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_badge_id')->constrained('student_badges')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('badge_id')->constrained('badges')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->foreignId('operated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code', 50)->nullable();
            $table->text('reason_detail')->nullable();
            $table->timestamp('event_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'event_at'], 'student_badge_events_student_date_index');
            $table->index(['badge_id', 'event_at'], 'student_badge_events_badge_date_index');
            $table->index(['event_type', 'event_at'], 'student_badge_events_type_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_badge_events');
    }
};
