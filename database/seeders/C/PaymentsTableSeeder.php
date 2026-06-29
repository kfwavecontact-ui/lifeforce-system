<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentsTableSeeder extends Seeder
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

        $paymentId = 1;
        $invoiceId = 1;

        foreach ([4, 5, 6] as $month) {
            foreach ($studentPrices as $studentId => $monthlyFee) {

                $hasAdmissionFee = $month === 4 && in_array($studentId, [1001, 1004, 1007, 1010], true);
                $subtotal = $monthlyFee + ($hasAdmissionFee ? 11000 : 0);
                $discount = $studentId % 4 === 0 ? 1000 : 0;
                $paymentAmount = $subtotal - $discount;

                $isUnpaid = $month === 6 && in_array($studentId, [1004, 1008, 1012], true);

                if (!$isUnpaid) {
                    $rows[] = [
                        'id' => $paymentId++,
                        'invoice_id' => $invoiceId,
                        'student_id' => $studentId,
                        'payment_method_id' => ($studentId % 3) + 1,
                        'payment_date' => sprintf('2026-%02d-27', $month),
                        'payment_amount' => $paymentAmount,
                        'payment_status' => 'confirmed',
                        'transaction_no' => sprintf('PAY-%06d', $invoiceId),
                        'confirmed_by' => null,
                        'confirmed_at' => now(),
                        'note' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $invoiceId++;
            }
        }

        DB::table('payments')->insert($rows);
    }
}