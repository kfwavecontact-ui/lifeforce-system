<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopProductImagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_product_images')->insert([
            [
                'id' => 1,
                'shop_product_id' => 1,
                'image_url' => '/shop/products/memory_beginner.png',
                'alt_text' => '瞬間記憶トレーニング教材 初級',
                'display_order' => 1,
                'is_main' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
