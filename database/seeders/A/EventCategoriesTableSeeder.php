<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventCategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_categories')->insert([
            [
                'id' => 1,
                'name' => '大会イベント',
                'description' => '順位や結果を競うイベント',
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'シーズンイベント',
                'description' => '季節イベント',
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => '体験・説明会',
                'description' => '集客・体験イベント',
                'display_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'ワークショップ',
                'description' => '体験型学習イベント',
                'display_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
