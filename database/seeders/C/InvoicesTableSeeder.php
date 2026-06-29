<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoicesTableSeeder extends Seeder
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

        $id = 1;

        foreach ([4, 5, 6] as $month) {
            foreach ($studentPrices as $studentId => $monthlyFee) {
                $hasAdmissionFee = $month === 4 && in_array($studentId, [1001, 1004, 1007, 1010], true);
                $admissionFee = $hasAdmissionFee ? 11000 : 0;

                $subtotal = $monthlyFee + $admissionFee;
                $discountAmount = $studentId % 4 === 0 ? 1000 : 0;
                $totalAmount = $subtotal - $discountAmount;

                $isUnpaid = $month === 6 && in_array($studentId, [1004, 1008, 1012], true);

                $rows[] = [
                    'id' => $id++,
                    'student_id' => $studentId,
                    'payment_method_id' => ($studentId % 3) + 1,
                    'invoice_no' => sprintf('INV-2026%02d-%04d', $month, $studentId),
                    'billing_year' => 2026,
                    'billing_month' => $month,
                    'issue_date' => sprintf('2026-%02d-01', $month),
                    'due_date' => sprintf('2026-%02d-27', $month),
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => 0,
                    'total_amount' => $totalAmount,
                    'payment_status' => $isUnpaid ? 'unpaid' : 'paid',
                    'paid_at' => $isUnpaid ? null : sprintf('2026-%02d-27 10:00:00', $month),
                    'note' => $discountAmount > 0 ? '兄弟割引適用' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('invoices')->insert($rows);
    }
}