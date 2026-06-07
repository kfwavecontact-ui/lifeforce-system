<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopOrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_orders')->insert([
            [
                'id' => 1,
                'user_id' => 5,
                'order_number' => 'SHOP-20260601-0001',
                'subtotal_amount' => 1200,
                'tax_amount' => 109,
                'total_amount' => 1200,
                'status' => 'paid',
                'ordered_at' => '2026-06-01 10:00:00',
                'paid_at' => '2026-06-01 10:03:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
