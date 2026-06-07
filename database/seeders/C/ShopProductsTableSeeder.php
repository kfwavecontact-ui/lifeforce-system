<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopProductsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_products')->insert([
            [
                'id' => 1,
                'shop_category_id' => 1,
                'name' => '瞬間記憶トレーニング教材 初級',
                'description' => '幼児向け教材',
                'product_type' => 'material',
                'price' => 1200,
                'tax_rate' => 10.0,
                'is_stock_managed' => true,
                'is_active' => true,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
