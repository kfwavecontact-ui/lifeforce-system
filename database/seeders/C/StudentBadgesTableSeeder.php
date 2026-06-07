<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentBadgesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_badges')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'badge_id' => 1,
                'acquired_at' => '2026-06-05 17:30:00',
                'is_displayed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
