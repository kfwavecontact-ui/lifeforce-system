<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningMaterialsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_materials')->insert([
            [
                'id' => 1,
                'code' => 'MAT_ORG_001',
                'category_id' => 5,
                'title' => '英検5級 単語特訓PDF',
                'created_by_user_id' => 1,
                'is_original' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
