<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutineTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('routine_types')->insert([
            [
                'id' => 1,
                'code' => 'reading',
                'name' => '読書',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'shogi_puzzle',
                'name' => '詰将棋',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'calculation',
                'name' => '計算',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'brain_training',
                'name' => '脳トレ',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
