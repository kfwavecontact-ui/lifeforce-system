<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoursePriceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('course_prices')->insert([

            // 脳開発コース
            [
                'course_id' => 1,
                'plan_type' => '週2',
                'price' => 18000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 1,
                'plan_type' => '週3',
                'price' => 26000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 1,
                'plan_type' => 'フリー',
                'price' => 35000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 脳開発＋将棋
            [
                'course_id' => 2,
                'plan_type' => '週2',
                'price' => 27000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 2,
                'plan_type' => '週3',
                'price' => 30000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 2,
                'plan_type' => 'フリー',
                'price' => 33000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 脳開発＋資格取得
            [
                'course_id' => 3,
                'plan_type' => '週2',
                'price' => 28000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 3,
                'plan_type' => '週3',
                'price' => 31000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 3,
                'plan_type' => 'フリー',
                'price' => 34000,
                'is_recommended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 脳開発＋将棋＋資格取得
            [
                'course_id' => 4,
                'plan_type' => '週2',
                'price' => 32000,
                'is_recommended' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 4,
                'plan_type' => '週3',
                'price' => 35000,
                'is_recommended' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'course_id' => 4,
                'plan_type' => 'フリー',
                'price' => 38000,
                'is_recommended' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);
    }
}

