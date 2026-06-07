<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentDiscountsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_discounts')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'discount_id' => 1,
                'start_date' => '2026-04-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
                'applied_reason' => '兄弟在籍',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
