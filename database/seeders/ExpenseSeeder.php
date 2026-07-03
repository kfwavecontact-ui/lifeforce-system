<?php

namespace Database\Seeders;

use App\Enums\AccountTransactionSourceType;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\Expense;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $accountCategory = AccountCategory::where('code', 'expense')
                ->orWhere('name', '経費')
                ->first();

            if (! $accountCategory) {
                return;
            }

            $paymentMethodId = PaymentMethod::query()
                ->where('code', 'cash')
                ->orWhere('name', '現金')
                ->value('id');

            $rows = [
                [
                    'expense_code' => 'EXP-202607-001',
                    'expense_category' => ExpenseCategory::TeachingMaterial->value,
                    'expense_title' => '脳開発プリント教材印刷費',
                    'vendor_name' => '柏教材印刷',
                    'scheduled_date' => '2026-07-05',
                    'paid_at' => '2026-07-05',
                    'amount' => 18000,
                    'tax_amount' => 1800,
                    'total_amount' => 19800,
                    'payment_status' => ExpenseStatus::Paid->value,
                    'memo' => '7月分教材印刷費',
                ],
                [
                    'expense_code' => 'EXP-202607-002',
                    'expense_category' => ExpenseCategory::Consumable->value,
                    'expense_title' => '教室用文房具購入',
                    'vendor_name' => 'オフィス用品店',
                    'scheduled_date' => '2026-07-08',
                    'paid_at' => '2026-07-08',
                    'amount' => 6200,
                    'tax_amount' => 620,
                    'total_amount' => 6820,
                    'payment_status' => ExpenseStatus::Paid->value,
                    'memo' => 'ホワイトボードマーカー、コピー用紙など',
                ],
                [
                    'expense_code' => 'EXP-202607-003',
                    'expense_category' => ExpenseCategory::Advertising->value,
                    'expense_title' => 'Instagram広告費',
                    'vendor_name' => 'Meta広告',
                    'scheduled_date' => '2026-07-15',
                    'paid_at' => null,
                    'amount' => 30000,
                    'tax_amount' => 3000,
                    'total_amount' => 33000,
                    'payment_status' => ExpenseStatus::Unpaid->value,
                    'memo' => '夏期体験募集広告',
                ],
                [
                    'expense_code' => 'EXP-202607-004',
                    'expense_category' => ExpenseCategory::Utilities->value,
                    'expense_title' => '教室電気代',
                    'vendor_name' => '電力会社',
                    'scheduled_date' => '2026-07-25',
                    'paid_at' => null,
                    'amount' => 22000,
                    'tax_amount' => 2200,
                    'total_amount' => 24200,
                    'payment_status' => ExpenseStatus::Unpaid->value,
                    'memo' => '7月請求分',
                ],
            ];

            foreach ($rows as $row) {
                $expense = Expense::updateOrCreate(
                    ['expense_code' => $row['expense_code']],
                    array_merge($row, [
                        'school_id' => 1,
                        'payment_method_id' => $paymentMethodId,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ])
                );

                $status = ExpenseStatus::from($expense->payment_status->value ?? $expense->payment_status);

                AccountTransaction::updateOrCreate(
                    [
                        'source_table' => AccountTransactionSourceType::Expense->value,
                        'source_id' => $expense->id,
                    ],
                    [
                        'scheduled_date' => $expense->scheduled_date,
                        'transaction_date' => $expense->paid_at,
                        'account_category_id' => $accountCategory->id,
                        'payment_method_id' => $expense->payment_method_id,
                        'transaction_name' => $expense->expense_title,
                        'amount' => $expense->total_amount,
                        'status' => $status->accountTransactionStatus(),
                        'memo' => $expense->memo,
                        'school_id' => $expense->school_id,
                        'student_id' => null,
                        'created_by' => $expense->created_by,
                    ]
                );
            }
        });
    }
}
