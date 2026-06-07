<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DailyRoutineStatusesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('daily_routine_statuses')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'student_routine_item_id' => 1,
                'target_date' => '2026-06-01',
                'status' => 'completed',
                'progress_minutes' => 10,
                'progress_count' => 0,
                'correct_count' => 0,
                'streak_correct_count' => 0,
                'completed_at' => '2026-06-01 18:00:00',
                'approved_at' => '2026-06-01 18:05:00',
                'approved_by' => 4,
                'completion_note' => '達成',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
