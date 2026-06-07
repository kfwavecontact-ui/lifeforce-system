<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutineCompletionTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('routine_completion_types')->truncate();

        DB::table('routine_completion_types')->insert([
            [
                'id' => 1,
                'code' => 'time',
                'name' => '時間',
                'description' => '指定された時間以上学習した場合に達成とする',
                'unit' => '分',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'count',
                'name' => '回数',
                'description' => '指定された回数以上実施した場合に達成とする',
                'unit' => '回',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'question_count',
                'name' => '問題数',
                'description' => '指定された問題数以上解いた場合に達成とする',
                'unit' => '問',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'accuracy',
                'name' => '正答率',
                'description' => '指定された正答率以上の場合に達成とする',
                'unit' => '%',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'code' => 'streak',
                'name' => '連続達成',
                'description' => '指定された日数以上連続で達成した場合に達成とする',
                'unit' => '日',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'code' => 'teacher_approval',
                'name' => '先生承認',
                'description' => '先生または管理者が承認した場合に達成とする',
                'unit' => '承認',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}