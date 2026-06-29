<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'tuition_fee', 'name' => '授業料売上', 'transaction_type' => '収益', 'sort_order' => 10],
            ['code' => 'admission_fee', 'name' => '入会金売上', 'transaction_type' => '収益', 'sort_order' => 20],
            ['code' => 'shop_sales', 'name' => 'ショップ売上', 'transaction_type' => '収益', 'sort_order' => 30],
            ['code' => 'event_sales', 'name' => 'イベント売上', 'transaction_type' => '収益', 'sort_order' => 40],
            ['code' => 'spot_sales', 'name' => 'スポット売上', 'transaction_type' => '収益', 'sort_order' => 50],
            ['code' => 'other_income', 'name' => 'その他売上', 'transaction_type' => '収益', 'sort_order' => 90],
            ['code' => 'refund', 'name' => '返金', 'transaction_type' => '費用', 'sort_order' => 100],
            ['code' => 'point_cost', 'name' => 'ポイント商品費用', 'transaction_type' => '費用', 'sort_order' => 110],
            ['code' => 'general_expense', 'name' => '経費', 'transaction_type' => '費用', 'sort_order' => 120],
            ['code' => 'other_expense', 'name' => 'その他費用', 'transaction_type' => '費用', 'sort_order' => 190],
        ];

        foreach ($categories as $category) {
            DB::table('account_categories')->updateOrInsert(
                ['code' => $category['code']],
                array_merge($category, [
                    'is_active' => true,
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}