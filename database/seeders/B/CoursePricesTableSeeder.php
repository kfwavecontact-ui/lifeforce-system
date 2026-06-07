<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoursePricesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('course_prices')->insert([
            [
                'id' => 1,
                'course_id' => 1,
                'attendance_type' => 'twice_weekly',
                'monthly_fee' => 18000,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'course_id' => 1,
                'attendance_type' => 'three_times_weekly',
                'monthly_fee' => 26000,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'course_id' => 2,
                'attendance_type' => 'twice_weekly',
                'monthly_fee' => 27000,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'course_id' => 3,
                'attendance_type' => 'twice_weekly',
                'monthly_fee' => 32000,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
