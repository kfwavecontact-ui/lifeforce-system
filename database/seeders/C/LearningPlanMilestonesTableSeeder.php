<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningPlanMilestonesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_plan_milestones')->insert([
            [
                'id' => 1,
                'learning_plan_id' => 1,
                'title' => '基礎固め',
                'target_date' => '2026-05-31',
                'status' => 'in_progress',
                'progress_rate' => 60,
                'sort_order' => 1,
                'note' => '単語・文法基礎',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
