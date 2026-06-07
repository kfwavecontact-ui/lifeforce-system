<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('invoices')->insert([
            [
                'id' => 1,
                'student_id' => 1,
                'payment_method_id' => 1,
                'invoice_no' => 'INV-202606-0001',
                'billing_year' => 2026,
                'billing_month' => 6,
                'issue_date' => '2026-06-01',
                'due_date' => '2026-06-27',
                'subtotal' => 18000,
                'discount_amount' => 1000,
                'tax_amount' => 1700,
                'total_amount' => 18700,
                'payment_status' => 'unpaid',
                'paid_at' => '2026-06-27 10:00:00',
                'note' => '兄弟割引適用',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
