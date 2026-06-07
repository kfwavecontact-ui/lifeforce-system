<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutineContentsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('routine_contents')->truncate();

        DB::table('routine_contents')->insert([

            [
                'id' => 1,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '数字瞬間記憶',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 2,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => 'ストループ課題',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 3,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => 'シルエットクイズ',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 4,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '位置記憶',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 5,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '数列推理',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 6,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '画像瞬間記憶',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 7,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '語彙連想ゲーム',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 8,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '早口言葉聴写',
                'sort_order' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 9,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '聴覚選択注意',
                'sort_order' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 10,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '漢字ジグソーパズル',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 11,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => 'くるたん',
                'sort_order' => 11,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 12,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '四則計算パズル',
                'sort_order' => 12,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 13,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => 'マトリクスパズル',
                'sort_order' => 13,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 14,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '九九高速反応',
                'sort_order' => 14,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 15,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '瞬間比較判断',
                'sort_order' => 15,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 16,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => 'フラッシュ暗算',
                'sort_order' => 16,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 17,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '不足数検索',
                'sort_order' => 17,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 18,
                'category_code' => 'brain_training',
                'category_name' => '脳開発',
                'name' => '短期記憶',
                'sort_order' => 18,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);
    }
}