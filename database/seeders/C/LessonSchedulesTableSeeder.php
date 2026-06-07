<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LessonSchedulesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('lesson_schedules')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'classroom_id' => 1,
                'course_id' => 1,
                'lesson_type_id' => 1,
                'teacher_user_id' => 4,
                'title' => '脳開発 火曜18時クラス',
                'day_of_week' => 2,
                'start_time' => '18:00',
                'end_time' => '19:00',
                'start_date' => '2026-04-01',
                'end_date' => '2026-12-31',
                'recurrence_type' => 'weekly',
                'max_students' => 8,
                'note' => '毎週火曜実施',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
