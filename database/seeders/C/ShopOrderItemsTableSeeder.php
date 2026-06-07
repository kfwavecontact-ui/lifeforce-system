<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopOrderItemsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_order_items')->insert([
            [
                'id' => 1,
                'shop_order_id' => 1,
                'shop_product_id' => 1,
                'product_name' => '瞬間記憶トレーニング教材 初級',
                'quantity' => 1,
                'unit_price' => 1200,
                'total_price' => 1200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
