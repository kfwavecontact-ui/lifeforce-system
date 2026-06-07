<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningPlanProgressLogsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_plan_progress_logs')->insert([
            [
                'id' => 1,
                'learning_plan_id' => 1,
                'learning_plan_milestone_id' => 1,
                'learning_plan_task_id' => 1,
                'student_id' => 1,
                'recorded_by_user_id' => 4,
                'progress_rate' => 60,
                'comment' => '単語学習を継続中',
                'recorded_at' => '2026-05-10 18:30:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
