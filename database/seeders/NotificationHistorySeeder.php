<?php

namespace Database\Seeders;

use App\Models\NotificationHistory;
use App\Models\NotificationMaster;
use Illuminate\Database\Seeder;

class NotificationHistorySeeder extends Seeder
{
    public function run(): void
    {
        $lessonBefore = NotificationMaster::where('code', 'lesson_before')->first();
        $badgeEarned = NotificationMaster::where('code', 'badge_earned')->first();
        $paymentOverdue = NotificationMaster::where('code', 'payment_overdue')->first();

        $histories = [
            [
                'notification_master_id' => $lessonBefore?->id,
                'sender_user_id' => null,
                'sender_name' => 'システム',
                'sender_role' => 'system',
                'recipient_user_id' => 101,
                'recipient_name' => '田中 花子',
                'recipient_role' => 'parent',
                'channel' => 'email',
                'title' => '明日の授業のお知らせ',
                'body' => '明日の授業は17:00開始です。',
                'status' => 'sent',
                'sent_at' => now()->subDays(1),
                'read_at' => null,
                'related_type' => 'lesson',
                'related_id' => 1,
            ],
            [
                'notification_master_id' => $lessonBefore?->id,
                'sender_user_id' => null,
                'sender_name' => 'システム',
                'sender_role' => 'system',
                'recipient_user_id' => 102,
                'recipient_name' => '田中 太郎',
                'recipient_role' => 'student',
                'channel' => 'portal',
                'title' => '明日の授業のお知らせ',
                'body' => '明日の授業は17:00開始です。',
                'status' => 'read',
                'sent_at' => now()->subDays(1),
                'read_at' => now()->subHours(20),
                'related_type' => 'lesson',
                'related_id' => 1,
            ],
            [
                'notification_master_id' => $badgeEarned?->id,
                'sender_user_id' => null,
                'sender_name' => 'システム',
                'sender_role' => 'system',
                'recipient_user_id' => 102,
                'recipient_name' => '田中 太郎',
                'recipient_role' => 'student',
                'channel' => 'portal',
                'title' => '新しいバッジを獲得しました',
                'body' => '数字瞬間記憶 Lv1 バッジを獲得しました。',
                'status' => 'read',
                'sent_at' => now()->subHours(6),
                'read_at' => now()->subHours(5),
                'related_type' => 'badge',
                'related_id' => 1,
            ],
            [
                'notification_master_id' => $paymentOverdue?->id,
                'sender_user_id' => 15,
                'sender_name' => '佐藤 一郎',
                'sender_role' => 'school_manager',
                'recipient_user_id' => 201,
                'recipient_name' => '山田 花子',
                'recipient_role' => 'parent',
                'channel' => 'line',
                'title' => '未入金のお知らせ',
                'body' => '今月分のご入金が確認できていません。',
                'status' => 'failed',
                'sent_at' => now()->subHours(2),
                'read_at' => null,
                'related_type' => 'invoice',
                'related_id' => 1,
                'error_message' => 'LINE連携情報が未登録です。',
            ],
        ];

        foreach ($histories as $history) {
            NotificationHistory::create($history);
        }
    }
}