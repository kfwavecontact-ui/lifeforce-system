<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopProductSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shop_products')->truncate();

        $products = [
            ['数字瞬間記憶カード',1,1200],
            ['シルエットカード',1,980],
            ['位置記憶カード',2,980],
            ['語彙カード',2,1500],
            ['将棋セット',3,3800],
            ['えんぴつセット',4,350],
            ['LifeForceノート',4,280],
            ['脳トレ問題集①',5,1200],
            ['脳トレ問題集②',5,1500],
            ['オリジナルクリアファイル',7,500],
        ];

        foreach ($products as $i => $product) {

            DB::table('shop_products')->insert([
                'school_id' => 1,
                'category_id' => $product[1],
                'product_code' => 'PRD' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),

                'name' => $product[0],
                'description' => $product[0] . 'です。',

                'price' => $product[2],
                'purchase_price' => floor($product[2] * 0.6),

                'tax_rate' => 10,

                'stock_quantity' => rand(20,100),

                'is_stock_managed' => true,

                'point_reward' => floor($product[2] / 100),

                'point_price' => floor($product[2] / 10),

                'barcode' => null,

                'image_path' => null,

                'is_online' => true,

                'published_at' => now(),

                'sales_end_at' => null,

                'display_order' => $i + 1,

                'is_active' => true,

                'created_by' => 1,
                'updated_by' => 1,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}