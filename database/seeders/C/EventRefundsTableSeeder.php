<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventRefundsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_refunds')->insert([
            [
                'id' => 1,
                'event_payment_id' => 1,
                'payment_provider' => 'univapay',
                'provider_refund_id' => 'ref_test_001',
                'refund_amount' => 0,
                'refund_status' => 'requested',
                'refund_reason' => 'テスト用レコード',
                'refunded_at' => '2026-05-26 10:00:00',
                'handled_by' => 3,
                'raw_response' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
