<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningRoutineLogsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_routine_logs')->insert([
            [
                'id' => 1,
                'learning_routine_id' => 1,
                'student_id' => 1,
                'target_date' => '2026-06-01',
                'is_completed' => true,
                'completed_at' => '2026-06-01 19:30:00',
                'note' => '読書10分完了',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
