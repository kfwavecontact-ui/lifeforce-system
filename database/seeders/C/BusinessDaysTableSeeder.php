<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessDaysTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('business_days')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'day_of_week' => 1,
                'open_time' => '14:00',
                'close_time' => '21:00',
                'is_open' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'school_id' => 1,
                'day_of_week' => 0,
                'open_time' => '00:00',
                'close_time' => '00:00',
                'is_open' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
