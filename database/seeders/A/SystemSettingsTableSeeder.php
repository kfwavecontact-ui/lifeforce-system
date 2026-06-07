<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('system_settings')->insert([
            [
                'id' => 1,
                'setting_key' => 'default_tax_rate',
                'setting_value' => '10',
                'description' => '標準消費税率',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'setting_key' => 'invoice_due_day',
                'setting_value' => '27',
                'description' => '毎月の支払期限日',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'setting_key' => 'school_year_start_month',
                'setting_value' => '4',
                'description' => '年度開始月',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
