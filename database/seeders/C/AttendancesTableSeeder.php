<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendancesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('attendances')->insert([
            [
                'id' => 1,
                'student_lesson_reservation_id' => 1,
                'student_id' => 1,
                'lesson_session_id' => 1,
                'attendance_status' => 'present',
                'checked_in_at' => '2026-06-02 17:58:00',
                'checked_out_at' => '2026-06-02 19:02:00',
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'recorded_by_user_id' => 4,
                'note' => '集中して取り組めていた',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
