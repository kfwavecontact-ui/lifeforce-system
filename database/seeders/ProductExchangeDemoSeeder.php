<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * 商品交換所の画面確認用デモデータを登録します。
 *
 * 対象画面:
 * - システム ＞ 設定 ＞ マスタ管理 ＞ 商品カテゴリ
 * - システム ＞ 設定 ＞ ポイント商品管理
 *
 * 利用テーブル:
 * - shop_categories
 * - reward_categories
 * - reward_items
 * - reward_item_stocks
 *
 * 既存データを削除せず、名称・商品コードをキーに再実行可能な形で登録します。
 */
class ProductExchangeDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['shop_categories', 'reward_categories', 'reward_items'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("{$table} テーブルが存在しません。");
            }
        }

        DB::transaction(function (): void {
            $this->seedShopCategories();
            $rewardCategoryIds = $this->seedRewardCategories();
            $this->seedRewardItems($rewardCategoryIds);
        });
    }

    private function seedShopCategories(): void
    {
        $now = now();
        $categories = [
            ['name' => '教材・学習用品', 'description' => 'ワーク、カード、知育教材など', 'display_order' => 1],
            ['name' => '文房具', 'description' => '鉛筆、消しゴム、ノートなど', 'display_order' => 2],
            ['name' => '将棋用品', 'description' => '将棋盤、駒、関連グッズなど', 'display_order' => 3],
            ['name' => 'スクールグッズ', 'description' => 'オリジナルバッグやウェアなど', 'display_order' => 4],
            ['name' => 'イベント商品', 'description' => 'イベント・期間限定の商品', 'display_order' => 5],
            ['name' => 'その他', 'description' => '上記に該当しない商品', 'display_order' => 99],
        ];

        foreach ($categories as $category) {
            DB::table('shop_categories')->updateOrInsert(
                ['name' => $category['name']],
                $category + [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /** @return array<string,int> */
    private function seedRewardCategories(): array
    {
        $now = now();
        $categories = [
            ['name' => '文房具', 'description' => '学習に使える文房具', 'icon' => 'fa-pencil', 'color_code' => '#60a5fa', 'sort_order' => 1],
            ['name' => '将棋グッズ', 'description' => '将棋関連のオリジナルグッズ', 'icon' => 'fa-chess', 'color_code' => '#f59e0b', 'sort_order' => 2],
            ['name' => 'ごほうび', 'description' => 'がんばった子ども向けのごほうび', 'icon' => 'fa-gift', 'color_code' => '#f472b6', 'sort_order' => 3],
            ['name' => '教材', 'description' => '学習教材・知育グッズ', 'icon' => 'fa-book', 'color_code' => '#34d399', 'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            DB::table('reward_categories')->updateOrInsert(
                ['name' => $category['name']],
                $category + [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        return DB::table('reward_categories')
            ->whereIn('name', array_column($categories, 'name'))
            ->pluck('id', 'name')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param array<string,int> $categoryIds */
    private function seedRewardItems(array $categoryIds): void
    {
        $now = now();
        $items = [
            ['code' => 'RWD-0001', 'category' => '文房具', 'name' => 'えんぴつセット', 'description' => '学習用えんぴつ3本セット', 'points' => 150, 'cost' => 80, 'stock' => 50, 'reserved' => 4, 'alert' => 10, 'recommended' => true,  'new' => false, 'limited' => false, 'order' => 1],
            ['code' => 'RWD-0002', 'category' => '文房具', 'name' => '消しゴムセット', 'description' => 'よく消える消しゴム2個セット', 'points' => 180, 'cost' => 90, 'stock' => 40, 'reserved' => 2, 'alert' => 10, 'recommended' => false, 'new' => true,  'limited' => false, 'order' => 2],
            ['code' => 'RWD-0003', 'category' => '文房具', 'name' => 'オリジナル学習ノート', 'description' => '生きる力アカデミーの学習ノート', 'points' => 250, 'cost' => 120, 'stock' => 35, 'reserved' => 5, 'alert' => 8, 'recommended' => true, 'new' => true, 'limited' => false, 'order' => 3],
            ['code' => 'RWD-0004', 'category' => '将棋グッズ', 'name' => '将棋駒キーホルダー', 'description' => '将棋駒をモチーフにしたキーホルダー', 'points' => 300, 'cost' => 180, 'stock' => 25, 'reserved' => 3, 'alert' => 5, 'recommended' => true, 'new' => false, 'limited' => true, 'order' => 4],
            ['code' => 'RWD-0005', 'category' => '将棋グッズ', 'name' => '将棋ステッカーセット', 'description' => '将棋デザインのステッカー5枚セット', 'points' => 220, 'cost' => 70, 'stock' => 60, 'reserved' => 8, 'alert' => 10, 'recommended' => false, 'new' => false, 'limited' => false, 'order' => 5],
            ['code' => 'RWD-0006', 'category' => 'ごほうび', 'name' => 'ごほうびシールセット', 'description' => '達成を楽しく記録できるシールセット', 'points' => 120, 'cost' => 40, 'stock' => 80, 'reserved' => 12, 'alert' => 15, 'recommended' => false, 'new' => false, 'limited' => false, 'order' => 6],
            ['code' => 'RWD-0007', 'category' => 'ごほうび', 'name' => '限定チャレンジカード', 'description' => '期間限定デザインのチャレンジカード', 'points' => 400, 'cost' => 150, 'stock' => 8, 'reserved' => 3, 'alert' => 5, 'recommended' => true, 'new' => true, 'limited' => true, 'order' => 7],
            ['code' => 'RWD-0008', 'category' => '教材', 'name' => '知育パズル', 'description' => '考える力を育てる知育パズル', 'points' => 900, 'cost' => 420, 'stock' => 15, 'reserved' => 2, 'alert' => 3, 'recommended' => true, 'new' => false, 'limited' => false, 'order' => 8],
            ['code' => 'RWD-0009', 'category' => '教材', 'name' => '計算チャレンジカード', 'description' => '楽しく計算練習ができるカード教材', 'points' => 650, 'cost' => 260, 'stock' => 5, 'reserved' => 1, 'alert' => 5, 'recommended' => false, 'new' => true, 'limited' => false, 'order' => 9],
            ['code' => 'RWD-0010', 'category' => 'ごほうび', 'name' => '図書カード500円分', 'description' => '読書を応援する図書カード', 'points' => 2500, 'cost' => 500, 'stock' => 0, 'reserved' => 0, 'alert' => 2, 'recommended' => true, 'new' => false, 'limited' => true, 'order' => 10],
        ];

        foreach ($items as $item) {
            $categoryId = $categoryIds[$item['category']] ?? null;
            if (! $categoryId) {
                throw new RuntimeException("ポイント商品カテゴリ {$item['category']} が見つかりません。");
            }

            $values = [
                'reward_category_id' => $categoryId,
                'name' => $item['name'],
                'description' => $item['description'],
                'required_points' => $item['points'],
                'cost_price' => $item['cost'],
                'stock_quantity' => $item['stock'],
                'stock_alert_quantity' => $item['alert'],
                'is_stock_managed' => true,
                'sort_order' => $item['order'],
                'is_active' => true,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('reward_items', 'publication_status')) {
                $values += [
                    'publication_status' => 'published',
                    'published_at' => $now,
                    'publication_ended_at' => null,
                    'exchange_limit_per_student' => 2,
                    'exchange_limit_per_month' => 1,
                    'is_recommended' => $item['recommended'],
                    'is_new' => $item['new'],
                    'is_limited' => $item['limited'],
                ];
            }

            DB::table('reward_items')->updateOrInsert(
                Schema::hasColumn('reward_items', 'code')
                    ? ['code' => $item['code']]
                    : ['name' => $item['name']],
                $values + ['created_at' => $now]
            );

            $rewardItemId = (int) DB::table('reward_items')
                ->when(
                    Schema::hasColumn('reward_items', 'code'),
                    fn ($query) => $query->where('code', $item['code']),
                    fn ($query) => $query->where('name', $item['name'])
                )
                ->value('id');

            if (Schema::hasTable('reward_item_stocks')) {
                DB::table('reward_item_stocks')->updateOrInsert(
                    ['reward_item_id' => $rewardItemId],
                    [
                        'stock_quantity' => $item['stock'],
                        'reserved_quantity' => min($item['reserved'], $item['stock']),
                        'alert_quantity' => $item['alert'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }
}
