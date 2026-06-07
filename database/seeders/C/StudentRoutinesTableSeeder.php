<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentRoutinesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_routines')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'title' => '平日ルーティン',
                'description' => '毎日の基本学習',
                'is_active' => true,
                'start_date' => '2026-05-01',
                'end_date' => '2026-12-31',
                'created_by' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'student_id' => 1,
                'title' => '春休み読書チャレンジ',
                'description' => '春休み期間中の読書習慣化',
                'is_active' => false,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
                'created_by' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 3,
                'student_id' => 1,
                'title' => '英検5級対策',
                'description' => '英単語と音読を毎日実施',
                'is_active' => false,
                'start_date' => '2026-01-01',
                'end_date' => '2026-02-28',
                'created_by' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 4,
                'student_id' => 1,
                'title' => '冬休み将棋強化',
                'description' => '詰将棋と棋譜並べ',
                'is_active' => false,
                'start_date' => '2025-12-20',
                'end_date' => '2026-01-10',
                'created_by' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
