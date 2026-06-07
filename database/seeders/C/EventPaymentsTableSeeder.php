<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventPaymentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_payments')->insert([
            [
                'id' => 1,
                'event_application_id' => 1,
                'payment_provider' => 'univapay',
                'payment_method' => 'card',
                'provider_payment_id' => 'pay_test_001',
                'provider_checkout_id' => 'chk_test_001',
                'provider_customer_id' => 'cus_test_001',
                'amount' => 500,
                'currency' => 'JPY',
                'payment_status' => 'paid',
                'paid_at' => '2026-05-25 10:05:00',
                'failed_at' => '2026-05-25 10:06:00',
                'cancelled_at' => '2026-05-25 10:07:00',
                'raw_response' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
