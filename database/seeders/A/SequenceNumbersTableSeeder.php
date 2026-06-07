<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SequenceNumbersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('sequence_numbers')->insert([
            [
                'id' => 1,
                'sequence_key' => 'student_code',
                'prefix' => 'STU',
                'current_number' => 2,
                'padding_length' => 4,
                'description' => '生徒番号採番',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'sequence_key' => 'teacher_code',
                'prefix' => 'TEA',
                'current_number' => 1,
                'padding_length' => 4,
                'description' => '講師番号採番',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'sequence_key' => 'parent_code',
                'prefix' => 'PAR',
                'current_number' => 2,
                'padding_length' => 4,
                'description' => '保護者番号採番',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'sequence_key' => 'invoice_no_202605',
                'prefix' => 'INV-202605-',
                'current_number' => 1,
                'padding_length' => 4,
                'description' => '請求番号採番',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
