<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningMaterialVersionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_material_versions')->insert([
            [
                'id' => 1,
                'learning_material_id' => 1,
                'version_no' => 1,
                'title' => '英検5級 単語特訓PDF Ver1',
                'file_url' => '/materials/eiken5_vocab_v1.pdf',
                'description' => '初版',
                'published_at' => '2026-04-01 10:00:00',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
