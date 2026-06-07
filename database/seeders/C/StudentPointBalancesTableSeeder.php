<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentPointBalancesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_point_balances')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'current_points' => 230,
                'total_earned_points' => 530,
                'total_used_points' => 300,
                'updated_at' => now(),
            ],
        ]);
    }
}
