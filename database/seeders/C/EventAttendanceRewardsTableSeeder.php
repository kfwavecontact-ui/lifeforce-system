<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventAttendanceRewardsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_attendance_rewards')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'event_schedule_id' => 1,
                'event_participant_id' => 1,
                'student_id' => 1,
                'event_reward_id' => 1,
                'reward_type' => 'point',
                'points' => 10,
                'badge_id' => 1,
                'title_id' => 1,
                'granted_by' => 3,
                'granted_at' => '2026-06-07 12:10:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
