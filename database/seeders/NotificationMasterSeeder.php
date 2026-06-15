<?php

namespace Database\Seeders;

use App\Models\NotificationMaster;
use App\Models\RoleNotificationSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationMasterSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $notifications = [
                ['code' => 'lesson_before', 'name' => '授業前日通知', 'category' => '授業', 'description' => '授業前日に送信される通知', 'sort_order' => 10],
                ['code' => 'lesson_today', 'name' => '本日の授業通知', 'category' => '授業', 'description' => '授業当日に送信される通知', 'sort_order' => 20],
                ['code' => 'lesson_absent', 'name' => '欠席通知', 'category' => '授業', 'description' => '欠席登録時に送信される通知', 'sort_order' => 30],
                ['code' => 'makeup_needed', 'name' => '振替未設定通知', 'category' => '授業', 'description' => '欠席後、振替未設定時に送信される通知', 'sort_order' => 40],

                ['code' => 'routine_not_completed', 'name' => 'ルーティン未達通知', 'category' => 'ルーティン', 'description' => 'ルーティン未達時に送信される通知', 'sort_order' => 50],
                ['code' => 'routine_completed', 'name' => 'ルーティン達成通知', 'category' => 'ルーティン', 'description' => 'ルーティン達成時に送信される通知', 'sort_order' => 60],

                ['code' => 'plan_deadline_near', 'name' => '計画学習期限前通知', 'category' => '計画学習', 'description' => '計画学習の期限前に送信される通知', 'sort_order' => 70],
                ['code' => 'plan_overdue', 'name' => '計画学習期限超過通知', 'category' => '計画学習', 'description' => '計画学習の期限超過時に送信される通知', 'sort_order' => 80],

                ['code' => 'challenge_reserved', 'name' => 'チャレンジ予約完了通知', 'category' => 'チャレンジ', 'description' => 'チャレンジ予約完了時に送信される通知', 'sort_order' => 90],
                ['code' => 'challenge_before', 'name' => 'チャレンジ前日通知', 'category' => 'チャレンジ', 'description' => 'チャレンジ前日に送信される通知', 'sort_order' => 100],
                ['code' => 'challenge_passed', 'name' => 'チャレンジ合格通知', 'category' => 'チャレンジ', 'description' => 'チャレンジ合格時に送信される通知', 'sort_order' => 110],
                ['code' => 'challenge_failed', 'name' => 'チャレンジ不合格通知', 'category' => 'チャレンジ', 'description' => 'チャレンジ不合格時に送信される通知', 'sort_order' => 120],

                ['code' => 'badge_earned', 'name' => 'バッジ獲得通知', 'category' => 'バッジ', 'description' => 'バッジ獲得時に送信される通知', 'sort_order' => 130],
                ['code' => 'title_earned', 'name' => '称号獲得通知', 'category' => '称号', 'description' => '称号獲得時に送信される通知', 'sort_order' => 140],

                ['code' => 'event_recommended', 'name' => 'おすすめイベント通知', 'category' => 'イベント', 'description' => 'おすすめイベントがある場合に送信される通知', 'sort_order' => 150],
                ['code' => 'event_reserved', 'name' => 'イベント申込完了通知', 'category' => 'イベント', 'description' => 'イベント申込完了時に送信される通知', 'sort_order' => 160],

                ['code' => 'invoice_created', 'name' => '請求書発行通知', 'category' => '会計', 'description' => '請求書発行時に送信される通知', 'sort_order' => 170],
                ['code' => 'payment_confirmed', 'name' => '入金確認通知', 'category' => '会計', 'description' => '入金確認時に送信される通知', 'sort_order' => 180],
                ['code' => 'payment_overdue', 'name' => '未入金通知', 'category' => '会計', 'description' => '未入金時に送信される通知', 'sort_order' => 190],

                ['code' => 'followup_reserved', 'name' => '対面フォロー予約完了通知', 'category' => '対面フォロー', 'description' => '対面フォロー予約完了時に送信される通知', 'sort_order' => 200],
                ['code' => 'followup_before', 'name' => '対面フォロー前日通知', 'category' => '対面フォロー', 'description' => '対面フォロー前日に送信される通知', 'sort_order' => 210],
                ['code' => 'followup_today', 'name' => '本日の対面フォロー通知', 'category' => '対面フォロー', 'description' => '対面フォロー当日に送信される通知', 'sort_order' => 220],

                ['code' => 'birthday', 'name' => '誕生日通知', 'category' => 'システム', 'description' => '生徒の誕生日に教室側へ送信される通知', 'sort_order' => 230],
            ];

            foreach ($notifications as $notification) {
                $master = NotificationMaster::updateOrCreate(
                    ['code' => $notification['code']],
                    [
                        'name' => $notification['name'],
                        'category' => $notification['category'],
                        'description' => $notification['description'],
                        'default_enabled' => true,
                        'is_active' => true,
                        'sort_order' => $notification['sort_order'],
                    ]
                );

                foreach (['student', 'parent', 'teacher', 'admin'] as $role) {
                    RoleNotificationSetting::updateOrCreate(
                        [
                            'notification_master_id' => $master->id,
                            'role' => $role,
                        ],
                        $this->defaultRoleSetting($notification['code'], $role)
                    );
                }
            }
        });
    }

    private function defaultRoleSetting(string $code, string $role): array
    {
        $settings = [
            'student' => [
                'lesson_before',
                'lesson_today',
                'routine_not_completed',
                'routine_completed',
                'plan_deadline_near',
                'plan_overdue',
                'challenge_reserved',
                'challenge_before',
                'challenge_passed',
                'challenge_failed',
                'badge_earned',
                'title_earned',
            ],
            'parent' => [
                'lesson_before',
                'lesson_today',
                'lesson_absent',
                'makeup_needed',
                'routine_not_completed',
                'routine_completed',
                'plan_deadline_near',
                'plan_overdue',
                'challenge_reserved',
                'challenge_before',
                'challenge_passed',
                'challenge_failed',
                'badge_earned',
                'title_earned',
                'event_recommended',
                'event_reserved',
                'invoice_created',
                'payment_confirmed',
                'payment_overdue',
                'followup_reserved',
                'followup_before',
                'followup_today',
            ],
            'teacher' => [
                'lesson_absent',
                'makeup_needed',
                'plan_overdue',
                'followup_reserved',
                'followup_before',
                'followup_today',
                'birthday',
            ],
            'admin' => [
                'payment_overdue',
                'birthday',
            ],
        ];

        $enabled = in_array($code, $settings[$role] ?? [], true);

        return [
            'portal_enabled' => $enabled,
            'email_enabled' => $role === 'parent' && in_array($code, [
                'lesson_before',
                'invoice_created',
                'payment_confirmed',
                'payment_overdue',
                'event_reserved',
            ], true),
            'line_enabled' => false,
            'push_enabled' => false,
            'is_active' => true,
        ];
    }
}