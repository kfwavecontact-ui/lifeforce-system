<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeAttemptReservationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('badge_attempt_reservations')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'badge_id' => 1,
                'reserved_at' => '2026-06-01 18:00:00',
                'challenge_date' => '2026-06-05',
                'status' => 'approved',
                'approved_teacher_id' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
