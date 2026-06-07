<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('students')->insert([
            [
                'id' => 1,
                'user_id' => 5,
                'school_id' => 1,
                'grade_id' => 5,
                'enrollment_status_id' => 2,
                'student_code' => 'STU0001',
                'last_name' => '山田',
                'first_name' => '太郎',
                'enrolled_at' => '2026-04-01',
                'withdrawn_at' => '2026-12-31',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
