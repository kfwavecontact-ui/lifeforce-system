<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopCartItemsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_cart_items')->insert([
            [
                'id' => 1,
                'shop_cart_id' => 1,
                'shop_product_id' => 1,
                'quantity' => 1,
                'unit_price' => 1200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
