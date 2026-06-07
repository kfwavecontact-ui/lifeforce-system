<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceItemsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('invoice_items')->insert([
            [
                'id' => 1,
                'invoice_id' => 1,
                'item_type' => 'tuition',
                'item_name' => '脳開発コース月謝',
                'quantity' => 1,
                'unit_price' => 18000,
                'amount' => 18000,
                'tax_rate' => 10,
                'note' => '6月分月謝',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
