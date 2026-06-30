<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\PaymentMethod;
use App\Models\School;
use App\Models\SpotSale;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpotSalesDummySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            AccountTransaction::where('source_table', 'spot_sales')->delete();
            SpotSale::query()->delete();

            $schoolIds = School::query()->pluck('id')->values();
            $studentIds = Student::query()->pluck('id')->values();
            $paymentMethodIds = PaymentMethod::query()->pluck('id')->values();

            if ($schoolIds->isEmpty()) {
                return;
            }

            $category = AccountCategory::query()
                ->where('code', 'spot_sales')
                ->orWhere('name', 'スポット売上')
                ->first();

            if (! $category) {
                $category = AccountCategory::query()->create([
                    'code' => 'spot_sales',
                    'name' => 'スポット売上',
                    'transaction_type' => 'income',
                    'sort_order' => 40,
                    'is_active' => true,
                    'note' => 'スポット売上用',
                ]);
            }

            $categories = [
                '単発講座',
                '教材費',
                '検定対策',
                '特別対応料',
                'その他',
            ];

            $titles = [
                '単発脳開発レッスン',
                '短期集中講座',
                '教材販売',
                '検定対策講座',
                '個別フォロー',
                '特別プリント代',
                '体験後追加講座',
                'イベント補講',
                '振替追加対応',
                'その他スポット売上',
            ];

            $statuses = ['paid', 'unpaid', 'scheduled'];

            for ($i = 1; $i <= 60; $i++) {
                $saleDate = Carbon::now()->subDays(rand(0, 420));
                $status = $statuses[$i % count($statuses)];
                $beforeDiscountAmount = rand(1, 8) * 1000;
                $discountAmount = $i % 7 === 0 ? 500 : 0;
                $taxAmount = (int) floor(($beforeDiscountAmount - $discountAmount) * 0.1);
                $totalAmount = $beforeDiscountAmount - $discountAmount + $taxAmount;

                $paymentDueDate = (clone $saleDate)->addDays(rand(0, 14));
                $paidAt = $status === 'paid'
                    ? (clone $paymentDueDate)->addDays(rand(0, 5))->setTime(rand(9, 19), rand(0, 59))
                    : null;

                $spotSale = SpotSale::query()->create([
                    'school_id' => $schoolIds->random(),
                    'student_id' => $studentIds->isNotEmpty() ? $studentIds->random() : null,
                    'payment_method_id' => $paymentMethodIds->isNotEmpty() ? $paymentMethodIds->random() : null,
                    'sale_code' => 'SPOT-' . now()->format('Ymd') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'category' => $categories[$i % count($categories)],
                    'sale_title' => $titles[$i % count($titles)],
                    'quantity' => rand(1, 3),
                    'sale_date' => $saleDate->toDateString(),
                    'payment_due_date' => $paymentDueDate->toDateString(),
                    'paid_at' => $paidAt,
                    'before_discount_amount' => $beforeDiscountAmount,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'payment_status' => $status,
                    'memo' => 'Phase3確認用スポット売上ダミーデータ',
                ]);

                AccountTransaction::query()->create([
                    'scheduled_date' => $spotSale->payment_due_date,
                    'transaction_date' => $spotSale->paid_at,
                    'account_category_id' => $category->id,
                    'payment_method_id' => $spotSale->payment_method_id,
                    'transaction_name' => $spotSale->sale_title,
                    'amount' => $spotSale->total_amount,
                    'before_discount_amount' => $spotSale->before_discount_amount,
                    'discount_amount' => $spotSale->discount_amount,
                    'status' => $spotSale->payment_status,
                    'memo' => $spotSale->memo,
                    'school_id' => $spotSale->school_id,
                    'student_id' => $spotSale->student_id,
                    'source_table' => 'spot_sales',
                    'source_id' => $spotSale->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}