<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RewardItemsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('reward_items')->insert([
            [
                'id' => 1,
                'reward_category_id' => 2,
                'name' => '将棋キーホルダー',
                'description' => '将棋駒デザインキーホルダー',
                'required_points' => 300,
                'cost_price' => 180,
                'stock_quantity' => 15,
                'stock_alert_quantity' => 3,
                'is_stock_managed' => true,
                'image_url' => '/images/rewards/shogi_keyholder.png',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'reward_category_id' => 1,
                'name' => 'えんぴつセット',
                'description' => '学習用えんぴつセット',
                'required_points' => 150,
                'cost_price' => 80,
                'stock_quantity' => 50,
                'stock_alert_quantity' => 10,
                'is_stock_managed' => true,
                'image_url' => '/images/rewards/pencil_set.png',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
