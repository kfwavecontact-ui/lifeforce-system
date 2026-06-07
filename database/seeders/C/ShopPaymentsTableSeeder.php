<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopPaymentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_payments')->insert([
            [
                'id' => 1,
                'shop_order_id' => 1,
                'user_id' => 5,
                'payment_provider' => 'univapay',
                'payment_method' => 'card',
                'provider_checkout_id' => 'chk_shop_001',
                'provider_payment_id' => 'pay_shop_001',
                'provider_customer_id' => 'cus_shop_001',
                'amount' => 1200,
                'currency' => 'JPY',
                'status' => 'succeeded',
                'paid_at' => '2026-06-01 10:03:00',
                'failed_at' => '2026-06-01 10:04:00',
                'cancelled_at' => '2026-06-01 10:05:00',
                'raw_response' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
