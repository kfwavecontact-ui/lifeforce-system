<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeRequirementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['content_clear','コンテンツクリア','指定した学習コンテンツをクリアした場合に達成します。',1,true],
            ['challenge_pass','チャレンジ合格','指定したチャレンジに合格した場合に達成します。',2,true],
            ['routine_complete','ルーティン達成','指定したルーティンを達成した場合に達成します。',3,true],
            ['total_points','累計ポイント到達','累計ポイントが指定値に到達した場合に達成します。',4,true],
            ['event_join','イベント参加','指定したイベントに参加した場合に達成します。',5,true],
            ['teacher_approval','講師承認','講師が承認した場合に達成します。',6,true],
            ['manual','手動付与','管理者が手動で付与する場合に使用します。',7,true],

            ['content_level_reached','コンテンツレベル到達','指定コンテンツで一定レベルに到達した場合に達成します。',8,false],
            ['content_clear_count','コンテンツ累計クリア回数','コンテンツの累計クリア回数が指定値に到達した場合に達成します。',9,false],
            ['content_study_time','コンテンツ累計学習時間','コンテンツの累計学習時間が指定値に到達した場合に達成します。',10,false],
            ['content_accuracy','コンテンツ正答率到達','指定した正答率に到達した場合に達成します。',11,false],
            ['content_consecutive_clear','コンテンツ連続クリア','指定回数連続でコンテンツをクリアした場合に達成します。',12,false],

            ['challenge_pass_count','チャレンジ累計合格回数','チャレンジ合格回数が指定値に到達した場合に達成します。',13,false],
            ['challenge_consecutive_pass','チャレンジ連続合格','指定回数連続でチャレンジに合格した場合に達成します。',14,false],

            ['routine_consecutive_complete','ルーティン連続達成','ルーティンを指定日数連続で達成した場合に達成します。',15,false],
            ['routine_complete_count','ルーティン累計達成回数','ルーティン達成回数が指定値に到達した場合に達成します。',16,false],

            ['learning_plan_complete','計画学習完了','計画学習を完了した場合に達成します。',17,false],
            ['learning_plan_complete_count','計画学習累計完了回数','計画学習の完了回数が指定値に到達した場合に達成します。',18,false],

            ['monthly_points','月間ポイント到達','月間ポイントが指定値に到達した場合に達成します。',19,false],
            ['current_points','保有ポイント到達','保有ポイントが指定値に到達した場合に達成します。',20,false],

            ['specific_badge_acquired','特定バッジ取得','指定したバッジを取得した場合に達成します。',21,false],
            ['badge_count_reached','バッジ取得数到達','取得バッジ数が指定値に到達した場合に達成します。',22,false],
            ['category_badge_complete','カテゴリバッジコンプリート','指定カテゴリのバッジをすべて取得した場合に達成します。',23,false],

            ['specific_title_acquired','特定称号取得','指定した称号を取得した場合に達成します。',24,false],
            ['title_count_reached','称号取得数到達','取得称号数が指定値に到達した場合に達成します。',25,false],

            ['shogi_rank_reached','棋力到達','指定した棋力に到達した場合に達成します。',26,false],
            ['shogi_promoted_grade','昇級','将棋で昇級した場合に達成します。',27,false],
            ['shogi_promoted_dan','昇段','将棋で昇段した場合に達成します。',28,false],
            ['shogi_challenge_pass','将棋チャレンジ合格','将棋系チャレンジに合格した場合に達成します。',29,false],

            ['qualification_acquired','資格取得','指定した資格を取得した場合に達成します。',30,false],
            ['qualification_pass','資格合格','指定した資格試験に合格した場合に達成します。',31,false],
            ['qualification_grade_reached','資格級到達','指定した資格級に到達した場合に達成します。',32,false],

            ['event_join_count','イベント参加回数','イベント参加回数が指定値に到達した場合に達成します。',33,false],
            ['event_full_attendance','イベント皆勤','指定イベントで皆勤した場合に達成します。',34,false],

            ['consecutive_learning_days','連続学習','指定日数連続で学習した場合に達成します。',35,false],
            ['consecutive_login_days','連続ログイン','指定日数連続でログインした場合に達成します。',36,false],
            ['total_learning_days','累計学習日数','累計学習日数が指定値に到達した場合に達成します。',37,false],

            ['friend_referral','友達紹介','友達紹介を行った場合に達成します。',38,false],
            ['trial_event_join','体験会参加','体験会に参加した場合に達成します。',39,false],
            ['special_mission_complete','特別ミッション達成','特別ミッションを達成した場合に達成します。',40,false],

            ['admin_grant','管理者付与','管理者が付与した場合に使用します。',41,false],
            ['limited_distribution','期間限定配布','期間限定で配布する場合に使用します。',42,false],
        ];

        foreach ($items as $index => $item) {
            DB::table('badge_requirement_types')->updateOrInsert(
                ['code' => $item[0]],
                [
                    'name' => $item[1],
                    'description' => $item[2],
                    'display_order' => $item[3],
                    'is_active' => $item[4],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}