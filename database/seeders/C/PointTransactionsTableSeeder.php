<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PointTransactionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('point_transactions')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'event_type' => 'routine_completed',
                'event_type_name' => 'ルーティン達成',
                'point_rule_code' => 'routine_30',
                'points' => 30,
                'balance_after' => 230,
                'reason' => '脳開発ルーティン達成',
                'related_id' => 1,
                'occurred_at' => '2026-06-01 18:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
