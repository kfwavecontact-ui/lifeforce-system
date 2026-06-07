<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TitlesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('titles')->insert([
            [
                'id' => 1,
                'name' => '瞬間記憶マスター',
                'description' => '瞬間記憶上級者',
                'image_url' => '/titles/memory_master.png',
                'rarity' => 'rare',
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => '将棋見習い',
                'description' => '将棋初級称号',
                'image_url' => '/titles/shogi_beginner.png',
                'rarity' => 'normal',
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
