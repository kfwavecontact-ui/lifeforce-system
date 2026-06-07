<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudyResultsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('study_results')->insert([
            [
                'id' => 1,
                'study_session_id' => 1,
                'total_questions' => 10,
                'correct_answers' => 8,
                'incorrect_answers' => 2,
                'accuracy_rate' => 80.0,
                'streak_correct_count' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
