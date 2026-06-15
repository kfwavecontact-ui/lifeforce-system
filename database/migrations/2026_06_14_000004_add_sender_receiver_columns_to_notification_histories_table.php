<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_histories', function (Blueprint $table) {

            $table->unsignedBigInteger('sender_user_id')
                ->nullable()
                ->after('notification_master_id')
                ->comment('送信者ID');

            $table->string('sender_name', 100)
                ->nullable()
                ->after('sender_user_id')
                ->comment('送信者名');

            $table->string('sender_role', 50)
                ->nullable()
                ->after('sender_name')
                ->comment('送信者ロール');

            $table->unsignedBigInteger('recipient_user_id')
                ->nullable()
                ->after('sender_role')
                ->comment('受信者ID');

            $table->index('sender_user_id');
            $table->index('sender_role');
            $table->index('recipient_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('notification_histories', function (Blueprint $table) {

            $table->dropIndex(['sender_user_id']);
            $table->dropIndex(['sender_role']);
            $table->dropIndex(['recipient_user_id']);

            $table->dropColumn([
                'sender_user_id',
                'sender_name',
                'sender_role',
                'recipient_user_id',
            ]);
        });
    }
};