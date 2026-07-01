<?php

namespace Database\Seeders;

use App\Enums\RefundSourceType;
use App\Enums\RefundStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RefundSeeder extends Seeder
{
    private int $createdCount = 0;
    private int $targetCount = 100;
    private ?int $userId = null;

    public function run(): void
    {
        DB::transaction(function () {
            $this->deleteOldDummyData();
            $this->syncPostgresSequence('refunds');

            $this->userId = DB::table('users')->orderBy('id')->value('id');

            $this->createTuitionEnrollmentRefunds(20);
            $this->createShopRefunds(20);
            $this->createEventRefunds(20);
            $this->createSpotRefunds(20);
            $this->createManualRefunds($this->targetCount - $this->createdCount);

            $this->syncPostgresSequence('refunds');
        });
    }

    private function createTuitionEnrollmentRefunds(int $count): void
    {
        $sources = DB::table('invoices')
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->select([
                'invoices.id',
                'invoices.student_id',
                'students.school_id',
                'invoices.payment_method_id',
                'invoices.total_amount',
                'invoices.due_date as base_date',
                'invoices.paid_at as paid_date',
            ])
            ->where('invoices.total_amount', '>', 0)
            ->orderBy('invoices.id')
            ->get();

        $this->createRefundsFromSources(
            sources: $sources,
            count: $count,
            sourceType: RefundSourceType::TuitionEnrollment,
            title: '授業料・入会金返金'
        );
    }

    private function createShopRefunds(int $count): void
    {
        $sources = DB::table('shop_orders')
            ->select([
                'id',
                'school_id',
                'student_id',
                'payment_method_id',
                'total_amount',
                'scheduled_date as base_date',
                'transaction_date as paid_date',
            ])
            ->where('total_amount', '>', 0)
            ->orderBy('id')
            ->get();

        $this->createRefundsFromSources(
            sources: $sources,
            count: $count,
            sourceType: RefundSourceType::Shop,
            title: 'ショップ返金'
        );
    }

    private function createEventRefunds(int $count): void
    {
        $sources = DB::table('event_applications')
            ->leftJoin('event_prices', 'event_prices.id', '=', 'event_applications.event_price_id')
            ->select([
                'event_applications.id',
                'event_applications.school_id',
                'event_applications.student_id',
                DB::raw('NULL as payment_method_id'),
                'event_prices.price as total_amount',
                'event_applications.applied_at as base_date',
                DB::raw('NULL as paid_date'),
            ])
            ->where('event_prices.price', '>', 0)
            ->orderBy('event_applications.id')
            ->get();

        $this->createRefundsFromSources(
            sources: $sources,
            count: $count,
            sourceType: RefundSourceType::Event,
            title: 'イベント返金'
        );
    }

    private function createSpotRefunds(int $count): void
    {
        $sources = DB::table('spot_sales')
            ->select([
                'id',
                'school_id',
                'student_id',
                'payment_method_id',
                'total_amount',
                'payment_due_date as base_date',
                'paid_at as paid_date',
            ])
            ->where('total_amount', '>', 0)
            ->orderBy('id')
            ->get();

        $this->createRefundsFromSources(
            sources: $sources,
            count: $count,
            sourceType: RefundSourceType::Spot,
            title: 'スポット返金'
        );
    }

    private function createManualRefunds(int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $students = DB::table('students')
            ->select('id', 'school_id')
            ->orderBy('id')
            ->get();

        $schoolIds = DB::table('schools')->orderBy('id')->pluck('id')->values();
        $paymentMethodIds = DB::table('payment_methods')->orderBy('id')->pluck('id')->values();

        if ($students->isEmpty() && $schoolIds->isEmpty()) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $student = $students->isNotEmpty() ? $students[$i % $students->count()] : null;
            $status = $this->statusForIndex($this->createdCount + 1);
            $scheduledDate = $this->scheduledDateForStatus($status);
            $refundedAt = $this->refundedAtForStatus($status, $scheduledDate);

            DB::table('refunds')->insert([
                'school_id' => $student?->school_id ?? $schoolIds[$i % $schoolIds->count()],
                'student_id' => $student?->id,
                'refund_code' => $this->nextRefundCode(),
                'refund_source_type' => RefundSourceType::Other->value,
                'refund_source_id' => null,
                'refund_amount' => $this->randomAmount(30000),
                'refund_method_id' => $paymentMethodIds->isNotEmpty()
                    ? $paymentMethodIds[$i % $paymentMethodIds->count()]
                    : null,
                'refund_reason' => $this->refundReason($this->createdCount + 1),
                'scheduled_date' => $scheduledDate->toDateString(),
                'refunded_at' => $refundedAt?->toDateString(),
                'status' => $status,
                'memo' => $this->memo($this->createdCount + 1),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->createdCount++;
        }
    }

    private function createRefundsFromSources($sources, int $count, RefundSourceType $sourceType, string $title): void
    {
        if ($sources->isEmpty() || $count <= 0) {
            return;
        }

        $paymentMethodIds = DB::table('payment_methods')->orderBy('id')->pluck('id')->values();

        for ($i = 0; $i < $count; $i++) {
            $source = $sources[$i % $sources->count()];
            $maxAmount = (int) round((float) ($source->total_amount ?? 0));

            if ($maxAmount <= 0) {
                continue;
            }

            $status = $this->statusForIndex($this->createdCount + 1);
            $scheduledDate = $this->scheduledDateForStatus($status, $source->base_date ?? null);
            $refundedAt = $this->refundedAtForStatus($status, $scheduledDate);

            DB::table('refunds')->insert([
                'school_id' => $source->school_id,
                'student_id' => $source->student_id,
                'refund_code' => $this->nextRefundCode(),
                'refund_source_type' => $sourceType->value,
                'refund_source_id' => $source->id,
                'refund_amount' => $this->randomAmount($maxAmount),
                'refund_method_id' => $source->payment_method_id
                    ?? ($paymentMethodIds->isNotEmpty() ? $paymentMethodIds[$i % $paymentMethodIds->count()] : null),
                'refund_reason' => $this->refundReason($this->createdCount + 1),
                'scheduled_date' => $scheduledDate->toDateString(),
                'refunded_at' => $refundedAt?->toDateString(),
                'status' => $status,
                'memo' => '【Phase4】' . $title . 'ダミーデータ ' . ($i + 1) . '：' . $this->memo($this->createdCount + 1),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->createdCount++;
        }
    }

    private function statusForIndex(int $index): string
    {
        return match (true) {
            $index % 20 === 0 => RefundStatus::Cancelled->value,
            $index % 5 === 0 => RefundStatus::Pending->value,
            default => RefundStatus::Completed->value,
        };
    }

    private function scheduledDateForStatus(string $status, mixed $baseDate = null): Carbon
    {
        if ($status === RefundStatus::Pending->value) {
            return now()->addDays(($this->createdCount % 30) + 1);
        }

        if ($baseDate) {
            $date = Carbon::parse($baseDate);

            if ($date->isFuture()) {
                return now()->subDays(($this->createdCount % 120) + 1);
            }

            return $date->copy()->addDays(($this->createdCount % 10) + 1);
        }

        return now()->subDays(($this->createdCount % 180) + 1);
    }

    private function refundedAtForStatus(string $status, Carbon $scheduledDate): ?Carbon
    {
        if ($status !== RefundStatus::Completed->value) {
            return null;
        }

        $refundedAt = $scheduledDate->copy()->addDays(($this->createdCount % 7));

        return $refundedAt->isFuture()
            ? now()->subDays($this->createdCount % 10)
            : $refundedAt;
    }

    private function randomAmount(int $maxAmount): int
    {
        $candidates = [500, 1000, 2000, 3000, 5000, 8000, 10000, 15000, 20000, 30000];
        $available = array_values(array_filter($candidates, fn (int $amount) => $amount <= $maxAmount));

        if ($available === []) {
            return max(1, $maxAmount);
        }

        return $available[($this->createdCount) % count($available)];
    }

    private function refundReason(int $index): string
    {
        $reasons = [
            '保護者都合によるキャンセル',
            'イベント中止',
            '商品不良',
            '二重決済',
            '入力ミス',
            '欠席による返金',
            'その他',
        ];

        return $reasons[($index - 1) % count($reasons)];
    }

    private function memo(int $index): ?string
    {
        $memos = [
            null,
            null,
            '電話連絡済',
            null,
            '教室対応',
            null,
            '校舎長承認済',
            null,
            null,
            '返金内容確認済',
        ];

        return $memos[($index - 1) % count($memos)];
    }

    private function nextRefundCode(): string
    {
        return 'REF-DUMMY-' . str_pad((string) ($this->createdCount + 1), 6, '0', STR_PAD_LEFT);
    }

    private function deleteOldDummyData(): void
    {
        DB::table('refunds')
            ->where('refund_code', 'like', 'REF-DUMMY-%')
            ->delete();
    }

    private function syncPostgresSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), true)");
    }
}
