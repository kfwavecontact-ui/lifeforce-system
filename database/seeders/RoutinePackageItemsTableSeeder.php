<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutinePackageItemsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('routine_package_items')->truncate();

        DB::table('routine_package_items')->insert([
            [
                'id' => 1,
                'routine_package_id' => 1,
                'routine_content_id' => 1,
                'item_name' => '数字瞬間記憶 Lv1',
                'target_grade' => '小1〜小3',
                'target_level' => 'Lv1',
                'tag' => '記憶力',
                'completion_type_id' => 1,
                'target_value' => 10,
                'estimated_minutes' => 10,
                'order_no' => 1,
                'is_required' => true,
                'memo' => '数字を短時間で覚える基礎トレーニング',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'routine_package_id' => 1,
                'routine_content_id' => 17,
                'item_name' => '不足数検索 Lv1',
                'target_grade' => '小1〜小3',
                'target_level' => 'Lv1',
                'tag' => '処理速度',
                'completion_type_id' => 3,
                'target_value' => 20,
                'estimated_minutes' => 10,
                'order_no' => 2,
                'is_required' => true,
                'memo' => '不足している数字を素早く見つける基礎トレーニング',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'routine_package_id' => 1,
                'routine_content_id' => 3,
                'item_name' => 'シルエットクイズ Lv1',
                'target_grade' => '小1〜小3',
                'target_level' => 'Lv1',
                'tag' => '認識力',
                'completion_type_id' => 3,
                'target_value' => 3,
                'estimated_minutes' => 10,
                'order_no' => 3,
                'is_required' => true,
                'memo' => '形を見て何かを判断する基礎トレーニング',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'routine_package_id' => 1,
                'routine_content_id' => 14,
                'item_name' => '九九高速反応 Lv1',
                'target_grade' => '小1〜小3',
                'target_level' => 'Lv1',
                'tag' => '計算力',
                'completion_type_id' => 4,
                'target_value' => 80,
                'estimated_minutes' => 10,
                'order_no' => 4,
                'is_required' => true,
                'memo' => '九九を素早く正確に答える基礎トレーニング',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}