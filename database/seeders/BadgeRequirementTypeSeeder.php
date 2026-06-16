<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeRequirementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'content_clear', 'name' => 'コンテンツクリア', 'description' => '指定した学習コンテンツをクリアした場合に達成します。', 'display_order' => 1, 'is_active' => true],
            ['code' => 'challenge_pass', 'name' => 'チャレンジ合格', 'description' => '指定したチャレンジに合格した場合に達成します。', 'display_order' => 2, 'is_active' => true],
            ['code' => 'routine_complete', 'name' => 'ルーティン達成', 'description' => '指定したルーティンを達成した場合に達成します。', 'display_order' => 3, 'is_active' => true],
            ['code' => 'total_points', 'name' => '累計ポイント到達', 'description' => '累計ポイントが指定値に到達した場合に達成します。', 'display_order' => 4, 'is_active' => true],
            ['code' => 'event_join', 'name' => 'イベント参加', 'description' => '指定したイベントに参加した場合に達成します。', 'display_order' => 5, 'is_active' => true],
            ['code' => 'teacher_approval', 'name' => '講師承認', 'description' => '講師が承認した場合に達成します。', 'display_order' => 6, 'is_active' => true],
            ['code' => 'manual', 'name' => '手動付与', 'description' => '管理者が手動で付与する場合に使用します。', 'display_order' => 7, 'is_active' => true],

            ['code' => 'content_level_reached', 'name' => 'コンテンツレベル到達', 'description' => '指定コンテンツで一定レベルに到達した場合に達成します。', 'display_order' => 8, 'is_active' => false],
            ['code' => 'content_clear_count', 'name' => 'コンテンツ累計クリア回数', 'description' => 'コンテンツの累計クリア回数が指定値に到達した場合に達成します。', 'display_order' => 9, 'is_active' => false],
            ['code' => 'content_study_time', 'name' => 'コンテンツ累計学習時間', 'description' => 'コンテンツの累計学習時間が指定値に到達した場合に達成します。', 'display_order' => 10, 'is_active' => false],
            ['code' => 'content_accuracy', 'name' => 'コンテンツ正答率到達', 'description' => '指定した正答率に到達した場合に達成します。', 'display_order' => 11, 'is_active' => false],
            ['code' => 'content_consecutive_clear', 'name' => 'コンテンツ連続クリア', 'description' => '指定回数連続でコンテンツをクリアした場合に達成します。', 'display_order' => 12, 'is_active' => false],

            ['code' => 'challenge_pass_count', 'name' => 'チャレンジ累計合格回数', 'description' => 'チャレンジ合格回数が指定値に到達した場合に達成します。', 'display_order' => 13, 'is_active' => false],
            ['code' => 'challenge_consecutive_pass', 'name' => 'チャレンジ連続合格', 'description' => '指定回数連続でチャレンジに合格した場合に達成します。', 'display_order' => 14, 'is_active' => false],

            ['code' => 'routine_consecutive_complete', 'name' => 'ルーティン連続達成', 'description' => 'ルーティンを指定日数連続で達成した場合に達成します。', 'display_order' => 15, 'is_active' => false],
            ['code' => 'routine_complete_count', 'name' => 'ルーティン累計達成回数', 'description' => 'ルーティン達成回数が指定値に到達した場合に達成します。', 'display_order' => 16, 'is_active' => false],

            ['code' => 'learning_plan_complete', 'name' => '計画学習完了', 'description' => '計画学習を完了した場合に達成します。', 'display_order' => 17, 'is_active' => false],
            ['code' => 'learning_plan_complete_count', 'name' => '計画学習累計完了回数', 'description' => '計画学習の完了回数が指定値に到達した場合に達成します。', 'display_order' => 18, 'is_active' => false],

            ['code' => 'monthly_points', 'name' => '月間ポイント到達', 'description' => '月間ポイントが指定値に到達した場合に達成します。', 'display_order' => 19, 'is_active' => false],
            ['code' => 'current_points', 'name' => '保有ポイント到達', 'description' => '保有ポイントが指定値に到達した場合に達成します。', 'display_order' => 20, 'is_active' => false],

            ['code' => 'specific_badge_acquired', 'name' => '特定バッジ取得', 'description' => '指定したバッジを取得した場合に達成します。', 'display_order' => 21, 'is_active' => false],
            ['code' => 'badge_count_reached', 'name' => 'バッジ取得数到達', 'description' => '取得バッジ数が指定値に到達した場合に達成します。', 'display_order' => 22, 'is_active' => false],
            ['code' => 'category_badge_complete', 'name' => 'カテゴリバッジコンプリート', 'description' => '指定カテゴリのバッジをすべて取得した場合に達成します。', 'display_order' => 23, 'is_active' => false],

            ['code' => 'specific_title_acquired', 'name' => '特定称号取得', 'description' => '指定した称号を取得した場合に達成します。', 'display_order' => 24, 'is_active' => false],
            ['code' => 'title_count_reached', 'name' => '称号取得数到達', 'description' => '取得称号数が指定値に到達した場合に達成します。', 'display_order' => 25, 'is_active' => false],

            ['code' => 'shogi_rank_reached', 'name' => '棋力到達', 'description' => '指定した棋力に到達した場合に達成します。', 'display_order' => 26, 'is_active' => false],
            ['code' => 'shogi_promoted_grade', 'name' => '昇級', 'description' => '将棋で昇級した場合に達成します。', 'display_order' => 27, 'is_active' => false],
            ['code' => 'shogi_promoted_dan', 'name' => '昇段', 'description' => '将棋で昇段した場合に達成します。', 'display_order' => 28, 'is_active' => false],
            ['code' => 'shogi_challenge_pass', 'name' => '将棋チャレンジ合格', 'description' => '将棋系チャレンジに合格した場合に達成します。', 'display_order' => 29, 'is_active' => false],

            ['code' => 'qualification_acquired', 'name' => '資格取得', 'description' => '指定した資格を取得した場合に達成します。', 'display_order' => 30, 'is_active' => false],
            ['code' => 'qualification_pass', 'name' => '資格合格', 'description' => '指定した資格試験に合格した場合に達成します。', 'display_order' => 31, 'is_active' => false],
            ['code' => 'qualification_grade_reached', 'name' => '資格級到達', 'description' => '指定した資格級に到達した場合に達成します。', 'display_order' => 32, 'is_active' => false],

            ['code' => 'event_join_count', 'name' => 'イベント参加回数', 'description' => 'イベント参加回数が指定値に到達した場合に達成します。', 'display_order' => 33, 'is_active' => false],
            ['code' => 'event_full_attendance', 'name' => 'イベント皆勤', 'description' => '指定イベントで皆勤した場合に達成します。', 'display_order' => 34, 'is_active' => false],

            ['code' => 'consecutive_learning_days', 'name' => '連続学習', 'description' => '指定日数連続で学習した場合に達成します。', 'display_order' => 35, 'is_active' => false],
            ['code' => 'consecutive_login_days', 'name' => '連続ログイン', 'description' => '指定日数連続でログインした場合に達成します。', 'display_order' => 36, 'is_active' => false],
            ['code' => 'total_learning_days', 'name' => '累計学習日数', 'description' => '累計学習日数が指定値に到達した場合に達成します。', 'display_order' => 37, 'is_active' => false],

            ['code' => 'friend_referral', 'name' => '友達紹介', 'description' => '友達紹介を行った場合に達成します。', 'display_order' => 38, 'is_active' => false],
            ['code' => 'trial_event_join', 'name' => '体験会参加', 'description' => '体験会に参加した場合に達成します。', 'display_order' => 39, 'is_active' => false],
            ['code' => 'special_mission_complete', 'name' => '特別ミッション達成', 'description' => '特別ミッションを達成した場合に達成します。', 'display_order' => 40, 'is_active' => false],

            ['code' => 'admin_grant', 'name' => '管理者付与', 'description' => '管理者が付与した場合に使用します。', 'display_order' => 41, 'is_active' => false],
            ['code' => 'limited_distribution', 'name' => '期間限定配布', 'description' => '期間限定で配布する場合に使用します。', 'display_order' => 42, 'is_active' => false],
        ];

        foreach ($items as $item) {
            DB::table('badge_requirement_types')->updateOrInsert(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'display_order' => $item['display_order'],
                    'is_active' => $item['is_active'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}