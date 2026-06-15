<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_notification_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('notification_master_id')
                ->constrained('notification_masters')
                ->cascadeOnDelete();

            $table->string('role', 50)->comment('ロール admin/teacher/student/parent');

            $table->boolean('portal_enabled')->default(false)->comment('ポータル通知');
            $table->boolean('email_enabled')->default(false)->comment('メール通知');
            $table->boolean('line_enabled')->default(false)->comment('LINE通知');
            $table->boolean('push_enabled')->default(false)->comment('ブラウザPush通知');

            $table->boolean('is_active')->default(true)->comment('有効フラグ');

            $table->timestamps();

            $table->unique(['notification_master_id', 'role'], 'role_notification_unique');
            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_notification_settings');
    }
};