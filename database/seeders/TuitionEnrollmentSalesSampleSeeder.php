<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TuitionEnrollmentSalesSampleSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::orderBy('id')->first();

        if (!$school) {
            return;
        }

        $students = Student::where('school_id', $school->id)
            ->orderBy('id')
            ->take(8)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $paymentMethods = PaymentMethod::orderBy('sort_order')->get();

        $bankTransfer = $paymentMethods->firstWhere('name', '銀行振込') ?? $paymentMethods->first();
        $cash = $paymentMethods->firstWhere('name', '現金') ?? $paymentMethods->first();
        $credit = $paymentMethods->firstWhere('name', 'クレジットカード') ?? $paymentMethods->first();

        $courses = [
            ['name' => '脳開発コース', 'type' => '週2', 'amount' => 18000],
            ['name' => '脳開発コース', 'type' => '週3', 'amount' => 26000],
            ['name' => '脳開発＋将棋コース', 'type' => '週2', 'amount' => 27000],
            ['name' => '脳開発＋資格取得コース', 'type' => '週2', 'amount' => 28000],
            ['name' => '脳開発＋将棋＋資格取得コース', 'type' => '週2', 'amount' => 32000],
        ];

        DB::transaction(function () use ($students, $bankTransfer, $cash, $credit, $courses) {
            foreach ($students as $index => $student) {
                $course = $courses[$index % count($courses)];
                $month = Carbon::now()->startOfMonth();

                $subtotal = $course['amount'];
                $discountAmount = $index % 3 === 0 ? 1000 : 0;
                $totalAmount = $subtotal - $discountAmount;

                $invoiceId = DB::table('invoices')->insertGetId([
                    'student_id' => $student->id,
                    'payment_method_id' => $bankTransfer?->id,
                    'invoice_no' => 'SAMPLE-' . now()->format('YmdHis') . '-' . $student->id,
                    'billing_year' => (int) $month->format('Y'),
                    'billing_month' => (int) $month->format('m'),
                    'issue_date' => $month->copy()->day(1)->toDateString(),
                    'due_date' => $month->copy()->day(27)->toDateString(),
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => 0,
                    'total_amount' => $totalAmount,
                    'payment_status' => $index % 4 === 0 ? 'unpaid' : 'paid',
                    'paid_at' => $index % 4 === 0 ? null : $month->copy()->day(27)->toDateString(),
                    'note' => '授業料・入会金売上画面確認用サンプル',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'item_type' => 'tuition',
                    'item_name' => $course['name'] . ' ' . $course['type'] . ' 授業料',
                    'quantity' => 1,
                    'unit_price' => $subtotal,
                    'amount' => $subtotal,
                    'tax_rate' => 0,
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($index === 0) {
                    DB::table('invoice_items')->insert([
                        'invoice_id' => $invoiceId,
                        'item_type' => 'admission',
                        'item_name' => '入会金',
                        'quantity' => 1,
                        'unit_price' => 11000,
                        'amount' => 11000,
                        'tax_rate' => 0,
                        'note' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($index % 4 !== 0) {
                    $method = match ($index % 3) {
                        0 => $cash,
                        1 => $credit,
                        default => $bankTransfer,
                    };

                    DB::table('payments')->insert([
                        'invoice_id' => $invoiceId,
                        'student_id' => $student->id,
                        'payment_method_id' => $method?->id,
                        'payment_date' => $month->copy()->day(27)->toDateString(),
                        'payment_amount' => $totalAmount,
                        'payment_status' => 'confirmed',
                        'transaction_no' => 'PAY-SAMPLE-' . $invoiceId,
                        'confirmed_by' => null,
                        'confirmed_at' => now(),
                        'note' => '授業料・入会金売上画面確認用サンプル入金',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}