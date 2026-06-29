<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoursesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('courses')->insert([
            [
                'id' => 1,
                'code' => 'brain',
                'name' => '脳開発コース',
                'sort_order' => 1,
                'description' => '脳開発を中心に学ぶ基本コース',
                'is_recommended' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'brain_shogi',
                'name' => '脳開発＋将棋コース',
                'sort_order' => 2,
                'description' => '脳開発と将棋を組み合わせたコース',
                'is_recommended' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'brain_certification',
                'name' => '脳開発＋資格取得コース',
                'sort_order' => 3,
                'description' => '脳開発と資格取得を組み合わせたコース',
                'is_recommended' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'brain_shogi_certification',
                'name' => '脳開発＋将棋＋資格取得コース',
                'sort_order' => 4,
                'description' => '脳開発・将棋・資格取得を組み合わせた推奨コース',
                'is_recommended' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}