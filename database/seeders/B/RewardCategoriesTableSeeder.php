<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RewardCategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('reward_categories')->insert([
            [
                'id' => 1,
                'name' => '文房具',
                'description' => '学習用文房具',
                'icon' => 'pencil',
                'color_code' => '#4CAF50',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => '将棋グッズ',
                'description' => '将棋関連グッズ',
                'icon' => 'crown',
                'color_code' => '#FF9800',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
