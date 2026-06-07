<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualificationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('qualifications')->insert([
            [
                'id' => 1,
                'code' => 'eiken_5',
                'name' => '英検5級',
                'category' => '英検',
                'level_name' => '5級',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'kanken_8',
                'name' => '漢検8級',
                'category' => '漢検',
                'level_name' => '8級',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
