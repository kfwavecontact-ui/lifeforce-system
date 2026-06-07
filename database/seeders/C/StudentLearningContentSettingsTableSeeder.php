<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentLearningContentSettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_learning_content_settings')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'learning_content_id' => 1,
                'is_enabled' => true,
                'assigned_by' => 4,
                'assigned_at' => '2026-05-01 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
