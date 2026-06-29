<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoursePricesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('course_prices')->insert([
            // 脳開発コース
            [
                'id' => 1,
                'course_id' => 1,
                'attendance_type' => '週2',
                'monthly_fee' => 18000,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'course_id' => 1,
                'attendance_type' => '週3',
                'monthly_fee' => 26000,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'course_id' => 1,
                'attendance_type' => 'フリー',
                'monthly_fee' => 35000,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 脳開発＋将棋コース
            [
                'id' => 4,
                'course_id' => 2,
                'attendance_type' => '週2',
                'monthly_fee' => 27000,
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'course_id' => 2,
                'attendance_type' => '週3',
                'monthly_fee' => 30000,
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'course_id' => 2,
                'attendance_type' => 'フリー',
                'monthly_fee' => 33000,
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 脳開発＋資格取得コース
            [
                'id' => 7,
                'course_id' => 3,
                'attendance_type' => '週2',
                'monthly_fee' => 28000,
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 8,
                'course_id' => 3,
                'attendance_type' => '週3',
                'monthly_fee' => 31000,
                'sort_order' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 9,
                'course_id' => 3,
                'attendance_type' => 'フリー',
                'monthly_fee' => 34000,
                'sort_order' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 脳開発＋将棋＋資格取得コース
            [
                'id' => 10,
                'course_id' => 4,
                'attendance_type' => '週2',
                'monthly_fee' => 32000,
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'course_id' => 4,
                'attendance_type' => '週3',
                'monthly_fee' => 35000,
                'sort_order' => 11,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 12,
                'course_id' => 4,
                'attendance_type' => 'フリー',
                'monthly_fee' => 38000,
                'sort_order' => 12,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}