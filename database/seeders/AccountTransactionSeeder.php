<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = DB::table('schools')->value('id');
        $userId = DB::table('users')->value('id');

        $studentIds = DB::table('students')
            ->orderBy('id')
            ->limit(4)
            ->pluck('id')
            ->values();

        if (! $schoolId) {
            $this->command?->warn('schools にデータがないため、AccountTransactionSeeder を中止しました。');
            return;
        }

        $student1 = $studentIds[0] ?? null;
        $student2 = $studentIds[1] ?? $student1;
        $student3 = $studentIds[2] ?? $student1;
        $student4 = $studentIds[3] ?? $student1;

        $rows = [
            ['scheduled_date' => '2026-06-27', 'transaction_date' => '2026-06-27', 'category' => 'tuition_fee', 'name' => '6月授業料', 'amount' => 32000, 'status' => 'confirmed', 'student_id' => $student1],
            ['scheduled_date' => '2026-06-27', 'transaction_date' => '2026-06-27', 'category' => 'tuition_fee', 'name' => '6月授業料', 'amount' => 26000, 'status' => 'confirmed', 'student_id' => $student2],
            ['scheduled_date' => '2026-06-27', 'transaction_date' => null, 'category' => 'tuition_fee', 'name' => '6月授業料', 'amount' => 35000, 'status' => 'planned', 'student_id' => $student3],
            ['scheduled_date' => '2026-06-01', 'transaction_date' => '2026-06-01', 'category' => 'admission_fee', 'name' => '入会金', 'amount' => 11000, 'status' => 'confirmed', 'student_id' => $student4],
            ['scheduled_date' => '2026-06-03', 'transaction_date' => '2026-06-03', 'category' => 'shop_sales', 'name' => '集中力トレーニングノート', 'amount' => 1200, 'status' => 'confirmed', 'student_id' => $student1],
            ['scheduled_date' => '2026-06-04', 'transaction_date' => '2026-06-04', 'category' => 'shop_sales', 'name' => 'IQパズルブック', 'amount' => 2500, 'status' => 'confirmed', 'student_id' => $student2],
            ['scheduled_date' => '2026-06-10', 'transaction_date' => '2026-06-10', 'category' => 'event_sales', 'name' => '夏休み脳開発イベント', 'amount' => 5000, 'status' => 'confirmed', 'student_id' => $student3],
            ['scheduled_date' => '2026-06-10', 'transaction_date' => '2026-06-10', 'category' => 'event_sales', 'name' => '夏休み脳開発イベント', 'amount' => 5000, 'status' => 'confirmed', 'student_id' => $student1],
            ['scheduled_date' => '2026-06-15', 'transaction_date' => '2026-06-15', 'category' => 'spot_sales', 'name' => '個別相談会', 'amount' => 3000, 'status' => 'confirmed', 'student_id' => $student4],
            ['scheduled_date' => '2026-07-01', 'transaction_date' => null, 'category' => 'refund', 'name' => 'イベント参加費返金', 'amount' => 5000, 'status' => 'planned', 'student_id' => $student1, 'memo' => '開催中止のため返金予定'],
            ['scheduled_date' => '2026-06-20', 'transaction_date' => '2026-06-20', 'category' => 'point_cost', 'name' => 'えんぴつセット交換', 'amount' => 300, 'status' => 'confirmed', 'student_id' => $student2],
            ['scheduled_date' => '2026-06-18', 'transaction_date' => '2026-06-18', 'category' => 'general_expense', 'name' => 'ホワイトボードマーカー購入', 'amount' => 1280, 'status' => 'confirmed', 'student_id' => null, 'memo' => '文房具'],
            ['scheduled_date' => '2026-06-19', 'transaction_date' => '2026-06-19', 'category' => 'general_expense', 'name' => '教材印刷用紙購入', 'amount' => 4850, 'status' => 'confirmed', 'student_id' => null, 'memo' => 'コピー用紙'],
            ['scheduled_date' => '2026-06-30', 'transaction_date' => null, 'category' => 'general_expense', 'name' => 'チラシ印刷代', 'amount' => 12000, 'status' => 'planned', 'student_id' => null, 'memo' => '7月募集用'],
            ['scheduled_date' => '2026-06-05', 'transaction_date' => '2026-06-05', 'category' => 'other_income', 'name' => '地域イベント協賛金', 'amount' => 10000, 'status' => 'confirmed', 'student_id' => null, 'memo' => '地域交流イベント'],
        ];

        foreach ($rows as $row) {
            $category = AccountCategory::where('code', $row['category'])->first();

            if (! $category) {
                continue;
            }

            AccountTransaction::create([
                'scheduled_date' => $row['scheduled_date'],
                'transaction_date' => $row['transaction_date'],
                'account_category_id' => $category->id,
                'payment_method_id' => null,
                'transaction_name' => $row['name'],
                'amount' => $row['amount'],
                'status' => $row['status'],
                'cancelled_reason' => null,
                'memo' => $row['memo'] ?? null,
                'school_id' => $schoolId,
                'student_id' => $row['student_id'],
                'source_table' => null,
                'source_id' => null,
                'related_transaction_id' => null,
                'created_by' => $userId,
            ]);
        }
    }
}