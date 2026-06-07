<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningContentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_contents')->insert([
            [
                'id' => 1,
                'category_code' => 'brain_training',
                'category_name' => '脳開発系',
                'name' => '脳開発',
                'description' => '思考力・記憶力トレーニング',
                'default_completion_type_id' => 1,
                'default_required_minutes' => 10,
                'default_required_count' => 0,
                'default_required_accuracy' => 0,
                'default_required_streak' => 0,
                'point_amount' => 30,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'category_code' => 'shogi',
                'category_name' => '将棋系',
                'name' => '詰将棋',
                'description' => '詰将棋問題演習',
                'default_completion_type_id' => 2,
                'default_required_minutes' => 0,
                'default_required_count' => 5,
                'default_required_accuracy' => 0,
                'default_required_streak' => 0,
                'point_amount' => 40,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'category_code' => 'reading',
                'category_name' => '読書系',
                'name' => '読書',
                'description' => '読書学習',
                'default_completion_type_id' => 1,
                'default_required_minutes' => 15,
                'default_required_count' => 0,
                'default_required_accuracy' => 0,
                'default_required_streak' => 0,
                'point_amount' => 20,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
