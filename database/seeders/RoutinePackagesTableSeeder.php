<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutinePackagesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('routine_packages')->truncate();

        DB::table('routine_packages')->insert([

            [
                'id' => 1,
                'name' => '低学年脳開発基礎パック',
                'description' => '小学校低学年向けの脳開発基礎パック',
                'category' => '脳開発',
                'target_grade' => '小1〜小3',
                'target_level' => '初級',
                'tag' => '脳開発',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 2,
                'name' => '低学年脳開発標準パック',
                'description' => '小学校低学年向けの標準パック',
                'category' => '脳開発',
                'target_grade' => '小1〜小3',
                'target_level' => '中級',
                'tag' => '脳開発',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 3,
                'name' => '高学年脳開発基礎パック',
                'description' => '小学校高学年向け脳開発パック',
                'category' => '脳開発',
                'target_grade' => '小4〜小6',
                'target_level' => '初級',
                'tag' => '脳開発',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 4,
                'name' => '記憶力強化パック',
                'description' => '記憶力向上に特化したパック',
                'category' => '能力強化',
                'target_grade' => '共通',
                'target_level' => '標準',
                'tag' => '記憶力',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 5,
                'name' => '処理速度強化パック',
                'description' => '処理速度向上に特化したパック',
                'category' => '能力強化',
                'target_grade' => '共通',
                'target_level' => '標準',
                'tag' => '処理速度',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 6,
                'name' => '将棋初級パック',
                'description' => '将棋初心者向けパック',
                'category' => '将棋',
                'target_grade' => '共通',
                'target_level' => '初級',
                'tag' => '将棋',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 7,
                'name' => '英検5級パック',
                'description' => '英検5級対策パック',
                'category' => '資格取得',
                'target_grade' => '共通',
                'target_level' => '英検5級',
                'tag' => '英語',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);
    }
}