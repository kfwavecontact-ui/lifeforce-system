<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningRoutinesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_routines')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'routine_type_id' => 1,
                'manager_user_id' => 4,
                'title' => '毎日10分読書',
                'description' => '読書習慣づくり',
                'frequency' => 'daily',
                'target_count' => 10,
                'target_unit' => 'minutes',
                'start_date' => '2026-04-01',
                'end_date' => '2026-12-31',
                'show_on_dashboard' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
