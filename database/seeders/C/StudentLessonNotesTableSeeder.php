<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentLessonNotesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_lesson_notes')->insert([
            [
                'id' => 1,
                'student_lesson_reservation_id' => 1,
                'student_id' => 1,
                'lesson_session_id' => 1,
                'note_type_id' => 2,
                'user_id' => 4,
                'body' => '集中して取り組めていた。',
                'is_private' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
