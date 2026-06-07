<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaysTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('holidays')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'holiday_date' => '2026-05-03',
                'name' => '憲法記念日',
                'holiday_type' => 'national_holiday',
                'is_closed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
