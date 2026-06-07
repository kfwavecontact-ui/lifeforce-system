<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolSettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('school_settings')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'setting_key' => 'business_start_time',
                'setting_value' => '14:00',
                'description' => '営業開始時刻',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
