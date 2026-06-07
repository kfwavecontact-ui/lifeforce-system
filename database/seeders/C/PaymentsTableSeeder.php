<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('payments')->insert([
            [
                'id' => 1,
                'invoice_id' => 1,
                'student_id' => 1,
                'payment_method_id' => 1,
                'payment_date' => '2026-06-27',
                'payment_amount' => 0,
                'payment_status' => 'pending',
                'transaction_no' => 'DEV-PAY-0001',
                'confirmed_by' => 1,
                'confirmed_at' => '2026-06-27 10:00:00',
                'note' => '未入金',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
