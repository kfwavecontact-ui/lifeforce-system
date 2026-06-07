<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LessonSessionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('lesson_sessions')->insert([
            [
                'id' => 1,
                'lesson_schedule_id' => 1,
                'calendar_event_id' => 1,
                'school_id' => 1,
                'classroom_id' => 1,
                'course_id' => 1,
                'lesson_type_id' => 1,
                'teacher_user_id' => 4,
                'title' => '脳開発 火曜18時クラス',
                'lesson_date' => '2026-06-02',
                'start_at' => '2026-06-02 18:00:00',
                'end_at' => '2026-06-02 19:00:00',
                'lesson_status' => 'scheduled',
                'max_students' => 8,
                'note' => '通常授業',
                'is_cancelled' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
