<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventRewardsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_rewards')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'reward_type' => 'point',
                'condition_type' => 'participation',
                'condition_value' => 'attended',
                'points' => 10,
                'badge_id' => 1,
                'title_id' => 1,
                'description' => '参加で10ポイント付与',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
