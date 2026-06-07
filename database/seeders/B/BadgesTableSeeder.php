<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('badges')->insert([
            [
                'id' => 1,
                'badge_category_id' => 1,
                'name' => '瞬間記憶 Lv1',
                'level' => 1,
                'description' => '瞬間記憶初級',
                'image_url' => '/badges/memory_lv1.png',
                'locked_image_url' => '/badges/locked.png',
                'point_reward' => 100,
                'is_limited' => false,
                'start_date' => null,
                'end_date' => null,
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'badge_category_id' => 2,
                'name' => '将棋思考 Lv1',
                'level' => 1,
                'description' => '将棋思考初級',
                'image_url' => '/badges/shogi_lv1.png',
                'locked_image_url' => '/badges/locked.png',
                'point_reward' => 100,
                'is_limited' => false,
                'start_date' => null,
                'end_date' => null,
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
