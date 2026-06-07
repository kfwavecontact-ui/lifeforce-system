<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lesson_makeups', function (Blueprint $table) {
            $table->id()->comment('一意の主キー');

            $table->unsignedBigInteger('student_id')
                ->comment('生徒ID');

            $table->unsignedBigInteger('attendance_id')
                ->comment('欠席した出席記録ID');

            $table->unsignedBigInteger('original_lesson_session_id')
                ->comment('元授業回ID');

            $table->unsignedBigInteger('makeup_lesson_session_id')
                ->nullable()
                ->comment('振替先授業回ID');

            $table->string('status')
                ->default('pending')
                ->comment('状態 pending/scheduled/completed/cancelled/expired');

            $table->string('absence_reason')
                ->nullable()
                ->comment('欠席理由');

            $table->text('memo')
                ->nullable()
                ->comment('振替メモ');

            $table->date('makeup_deadline')
                ->nullable()
                ->comment('振替期限');

            $table->unsignedBigInteger('requested_by_user_id')
                ->nullable()
                ->comment('振替申請者ユーザーID');

            $table->unsignedBigInteger('approved_by_user_id')
                ->nullable()
                ->comment('振替承認者ユーザーID');

            $table->timestamp('requested_at')
                ->nullable()
                ->comment('振替申請日時');

            $table->timestamp('scheduled_at')
                ->nullable()
                ->comment('振替確定日時');

            $table->timestamp('completed_at')
                ->nullable()
                ->comment('振替受講日時');

            $table->timestamps();

            $table->index('student_id');
            $table->index('attendance_id');
            $table->index('original_lesson_session_id');
            $table->index('makeup_lesson_session_id');
            $table->index('status');
            $table->index('makeup_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_makeups');
    }
};
