<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RewardExchangeRequestsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('reward_exchange_requests')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'reward_item_id' => 1,
                'request_points' => 300,
                'status' => 'delivered',
                'status_name' => '受渡済',
                'requested_at' => '2026-06-01 18:30:00',
                'approved_at' => '2026-06-01 18:40:00',
                'delivered_at' => '2026-06-01 19:00:00',
                'rejected_at' => null,
                'handled_by' => 3,
                'note' => '教室で受渡済',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
