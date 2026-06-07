<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutineCompletionLogsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('routine_completion_logs')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'student_routine_id' => 1,
                'student_routine_item_id' => 1,

                'completed_on' => now()->toDateString(),

                'actual_minutes' => 10,
                'actual_count' => 0,
                'actual_accuracy' => 0,

                'point_amount' => 30,

                'is_completed' => true,

                'approved_by' => 4,
                'approved_at' => now(),

                'remarks' => '毎日の読書達成',

                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}