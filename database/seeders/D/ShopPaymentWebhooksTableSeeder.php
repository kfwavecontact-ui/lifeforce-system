<?php

namespace Database\Seeders\D;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopPaymentWebhooksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_payment_webhooks')->insert([
            [
                'id' => 1,
                'shop_payment_id' => 1,
                'payment_provider' => 'univapay',
                'provider_event_id' => 'evt_shop_001',
                'provider_event_type' => 'payment.succeeded',
                'payload' => '{}',
                'status' => 'received',
                'received_at' => '2026-06-01 10:03:30',
                'processed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
