<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentCourseContractsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_course_contracts')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'course_id' => 1,
                'course_price_id' => 1,
                'contract_status' => 'active',
                'started_at' => '2026-04-01',
                'ended_at' => '2026-12-31',
                'monthly_fee' => 18000,
                'note' => '脳開発コース・週2契約',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
