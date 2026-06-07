<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningPlansTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_plans')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'learning_plan_type_id' => 1,
                'manager_user_id' => 4,
                'qualification_id' => 1,
                'title' => '英検5級合格計画',
                'goal' => '英検5級に合格する',
                'start_date' => '2026-04-01',
                'target_date' => '2026-07-15',
                'status' => 'in_progress',
                'progress_rate' => 30,
                'note' => '逆算型で学習管理',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
