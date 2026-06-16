<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeSeriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('badge_series')->updateOrInsert(
            ['id' => 1],
            [
                'badge_category_id' => 1,
                'name' => '数字瞬間記憶',
                'description' => 'テスト',
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}