<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeRewardsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('badge_rewards')->insert([
            [
                'id' => 1,
                'badge_id' => 1,
                'reward_type' => 'points',
                'reward_value' => '100',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'badge_id' => 2,
                'reward_type' => 'title',
                'reward_value' => '将棋見習い',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
