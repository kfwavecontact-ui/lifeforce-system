<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 一斉通知の送信対象条件を保存します。
 *
 * target_type は all/classroom/grade/course/student、recipient_type は
 * student/parent を想定します。実際の受信者は送信時に
 * notification_recipients へ展開します。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete();
            $table->string('target_type', 30)->comment('対象区分');
            $table->unsignedBigInteger('target_id')->nullable()->comment('対象ID');
            $table->string('recipient_type', 20)->comment('受信者区分 student/parent');
            $table->timestamps();

            $table->index(['notification_id', 'target_type']);
            $table->index(['target_type', 'target_id']);
            $table->index(['notification_id', 'recipient_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_targets');
    }
};
