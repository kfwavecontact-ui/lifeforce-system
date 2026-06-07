<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopProductStocksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_product_stocks')->insert([
            [
                'id' => 1,
                'shop_product_id' => 1,
                'stock_quantity' => 50,
                'reserved_quantity' => 0,
                'alert_quantity' => 5,
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
