<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningPlanTasksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_plan_tasks')->insert([
            [
                'id' => 1,
                'learning_plan_milestone_id' => 1,
                'title' => '英単語300語',
                'description' => '単語帳の基礎範囲を覚える',
                'due_date' => '2026-05-15',
                'status' => 'in_progress',
                'progress_rate' => 60,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
