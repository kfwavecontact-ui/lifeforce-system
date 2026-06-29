<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shop_product_categories')->truncate();

        $categories = [
            '教材',
            '脳開発教材',
            '将棋用品',
            '文房具',
            '書籍',
            'イベント用品',
            'グッズ',
            '知育玩具',
            '賞品',
            'その他',
        ];

        foreach ($categories as $index => $name) {
            DB::table('shop_product_categories')->insert([
                'school_id' => null,
                'category_code' => 'CAT' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'description' => $name . 'カテゴリ',
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}