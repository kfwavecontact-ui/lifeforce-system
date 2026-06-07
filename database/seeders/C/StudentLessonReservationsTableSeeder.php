<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentLessonReservationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_lesson_reservations')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'lesson_session_id' => 1,
                'original_reservation_id' => null,
                'reservation_status' => 'reserved',
                'reserved_at' => '2026-05-30 10:00:00',
                'cancelled_at' => '2026-06-01 10:00:00',
                'note' => '通常予約',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
