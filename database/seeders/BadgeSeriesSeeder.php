<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * バッジシリーズマスタSeeder。
 * カテゴリごとに、一覧表示・バッジ追加・編集で選択できるシリーズを登録する。
 */
class BadgeSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $seriesByCategory = [
            '脳開発' => [
                ['集中力', '集中力に関する達成バッジです。'],
                ['記憶力', '記憶力に関する達成バッジです。'],
                ['思考力', '思考力に関する達成バッジです。'],
                ['判断力', '判断力に関する達成バッジです。'],
            ],
            '継続' => [
                ['連続学習', '連続して学習した成果を表すバッジです。'],
                ['累計学習', '累計の学習日数や回数を表すバッジです。'],
                ['皆勤', '継続参加や皆勤を表すバッジです。'],
            ],
            '挑戦' => [
                ['チャレンジ', 'チャレンジへの参加や合格を表すバッジです。'],
                ['計画学習', '計画学習の完了や達成を表すバッジです。'],
                ['イベント', 'イベントへの参加や達成を表すバッジです。'],
            ],
            '将棋' => [
                ['詰将棋', '詰将棋の学習・達成を表すバッジです。'],
                ['棋力', '昇級・昇段・棋力到達を表すバッジです。'],
                ['将棋チャレンジ', '将棋チャレンジの合格を表すバッジです。'],
            ],
            '特別' => [
                ['がんばり賞', '講師や運営が評価して付与する特別バッジです。'],
                ['応援・協力', '仲間への応援や協力を表す特別バッジです。'],
                ['期間限定', 'キャンペーンや期間限定で付与するバッジです。'],
            ],
        ];

        $displayOrder = 1;

        foreach ($seriesByCategory as $categoryName => $seriesItems) {
            $categoryId = DB::table('badge_categories')->where('name', $categoryName)->value('id');

            if (!$categoryId) {
                continue;
            }

            foreach ($seriesItems as [$name, $description]) {
                DB::table('badge_series')->updateOrInsert(
                    [
                        'badge_category_id' => $categoryId,
                        'name' => $name,
                    ],
                    [
                        'description' => $description,
                        'display_order' => $displayOrder++,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
