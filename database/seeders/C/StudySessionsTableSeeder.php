<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudySessionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('study_sessions')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'learning_content_id' => 1,
                'started_at' => '2026-06-01 17:00:00',
                'ended_at' => '2026-06-01 17:10:00',
                'actual_minutes' => 10,
                'is_completed' => true,
                'last_activity_at' => '2026-06-01 17:09:30',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
