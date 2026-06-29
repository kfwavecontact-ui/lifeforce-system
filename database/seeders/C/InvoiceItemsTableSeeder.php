<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceItemsTableSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];

        $studentPrices = [
            1001 => 18000,
            1002 => 26000,
            1003 => 35000,
            1004 => 27000,
            1005 => 30000,
            1006 => 33000,
            1007 => 28000,
            1008 => 31000,
            1009 => 34000,
            1010 => 32000,
            1011 => 35000,
            1012 => 38000,
        ];

        $itemId = 1;
        $invoiceId = 1;

        foreach ([4, 5, 6] as $month) {
            foreach ($studentPrices as $studentId => $monthlyFee) {
                $rows[] = [
                    'id' => $itemId++,
                    'invoice_id' => $invoiceId,
                    'item_type' => 'tuition',
                    'item_name' => '授業料',
                    'quantity' => 1,
                    'unit_price' => $monthlyFee,
                    'amount' => $monthlyFee,
                    'tax_rate' => 0,
                    'note' => sprintf('2026年%d月授業料', $month),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($month === 4 && in_array($studentId, [1001, 1004, 1007, 1010], true)) {
                    $rows[] = [
                        'id' => $itemId++,
                        'invoice_id' => $invoiceId,
                        'item_type' => 'admission',
                        'item_name' => '入会金',
                        'quantity' => 1,
                        'unit_price' => 11000,
                        'amount' => 11000,
                        'tax_rate' => 0,
                        'note' => '入会金',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $invoiceId++;
            }
        }

        DB::table('invoice_items')->insert($rows);
    }
}