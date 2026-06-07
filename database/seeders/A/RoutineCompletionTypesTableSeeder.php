<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutineCompletionTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('routine_completion_types')->insert([
            [
                'id' => 1,
                'code' => 'time_based',
                'display_name' => '時間達成',
                'description' => '指定時間達成で完了',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'count_based',
                'display_name' => '回数達成',
                'description' => '指定回数達成で完了',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'accuracy_based',
                'display_name' => '正答率達成',
                'description' => '指定正答率で完了',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'teacher_approval',
                'display_name' => '教師承認',
                'description' => '教師承認で完了',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
