<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeachersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('teachers')->insert([
            [
                'id' => 1,
                'user_id' => 4,
                'employment_type_id' => 1,
                'teacher_status_id' => 1,
                'teacher_code' => 'TEA0001',
                'last_name' => '田中',
                'first_name' => '一郎',
                'phone_number' => '090-3333-3333',
                'hire_date' => '2026-04-01',
                'resignation_date' => '2026-12-31',
                'note' => '将棋担当',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
