<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('payment_methods')->insert([
            [
                'id' => 1,
                'code' => 'BANK_TRANSFER',
                'name' => '口座振替',
                'is_auto_payment' => true,
                'sort_order' => 1,
                'is_active' => true,
                'note' => '毎月自動引落',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'CREDIT_CARD',
                'name' => 'クレジットカード',
                'is_auto_payment' => true,
                'sort_order' => 2,
                'is_active' => true,
                'note' => 'オンライン決済',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'code' => 'CASH',
                'name' => '現金',
                'is_auto_payment' => false,
                'sort_order' => 3,
                'is_active' => true,
                'note' => '教室受付',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'code' => 'BANK',
                'name' => '銀行振込',
                'is_auto_payment' => false,
                'sort_order' => 4,
                'is_active' => true,
                'note' => '指定口座へ振込',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
