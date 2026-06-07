<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeChallengeLogsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('badge_challenge_logs')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'badge_id' => 1,
                'challenged_at' => '2026-06-05 17:00:00',
                'result' => 'passed',
                'score' => 95,
                'checked_teacher_id' => 4,
                'remarks' => '集中力良好',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
