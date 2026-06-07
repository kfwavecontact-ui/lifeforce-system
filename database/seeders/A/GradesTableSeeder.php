<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GradesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('grades')->truncate();

        DB::table('grades')->insert([
            ['id' => 1, 'code' => 'PRE', 'name' => '年少より下', 'sort_order' => 1, 'next_grade_id' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'K1', 'name' => '年少', 'sort_order' => 2, 'next_grade_id' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'K2', 'name' => '年中', 'sort_order' => 3, 'next_grade_id' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'code' => 'K3', 'name' => '年長', 'sort_order' => 4, 'next_grade_id' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'code' => 'E1', 'name' => '小1', 'sort_order' => 5, 'next_grade_id' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'code' => 'E2', 'name' => '小2', 'sort_order' => 6, 'next_grade_id' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'code' => 'E3', 'name' => '小3', 'sort_order' => 7, 'next_grade_id' => 8, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'code' => 'E4', 'name' => '小4', 'sort_order' => 8, 'next_grade_id' => 9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'code' => 'E5', 'name' => '小5', 'sort_order' => 9, 'next_grade_id' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'code' => 'E6', 'name' => '小6', 'sort_order' => 10, 'next_grade_id' => 11, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'code' => 'J1', 'name' => '中1', 'sort_order' => 11, 'next_grade_id' => 12, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'code' => 'J2', 'name' => '中2', 'sort_order' => 12, 'next_grade_id' => 13, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'code' => 'J3', 'name' => '中3', 'sort_order' => 13, 'next_grade_id' => 14, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'code' => 'H1', 'name' => '高1', 'sort_order' => 14, 'next_grade_id' => 15, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'code' => 'H2', 'name' => '高2', 'sort_order' => 15, 'next_grade_id' => 16, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'code' => 'H3', 'name' => '高3', 'sort_order' => 16, 'next_grade_id' => 16, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
    }
}
