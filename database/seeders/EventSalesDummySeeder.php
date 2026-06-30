<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class EventSalesDummySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $this->syncPostgresSequences();
            $this->deleteOldDummyData();

            $userId = $this->ensureUser($now);
            $eventCategoryId = $this->ensureEventCategory($now);
            $eventStatusId = $this->ensureEventStatus($now);
            $accountCategoryId = $this->ensureAccountCategory($now);
            $paymentMethods = $this->ensurePaymentMethods($now);
            $discounts = $this->ensureDiscounts($now);
            $events = $this->ensureEvents($eventCategoryId, $eventStatusId, $userId, $now);

            $students = DB::table('students')
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(30)
                ->get();

            if ($students->isEmpty()) {
                throw new RuntimeException('EventSalesDummySeeder: students が0件です。先に生徒データを用意してください。');
            }

            $firstSchoolId = DB::table('schools')->orderBy('id')->value('id');
            if (! $firstSchoolId) {
                throw new RuntimeException('EventSalesDummySeeder: schools が0件です。先に教室データを用意してください。');
            }

            $participationTypes = ['通常参加', '兄弟参加', '追加参加'];
            $statuses = ['paid', 'unpaid', 'paid', 'paid', 'cancelled'];
            $baseDate = Carbon::create(2025, 7, 5);

            for ($i = 1; $i <= 60; $i++) {
                $student = $students[($i - 1) % $students->count()];
                $event = $events[($i - 1) % count($events)];
                $participationType = $participationTypes[($i - 1) % count($participationTypes)];
                $paymentStatus = $statuses[($i - 1) % count($statuses)];
                $paymentMethod = $paymentMethods[($i - 1) % count($paymentMethods)];

                $scheduledDate = $baseDate->copy()
                    ->addDays(($i - 1) * 6)
                    ->toDateString();

                $paidDate = $paymentStatus === 'paid'
                    ? Carbon::parse($scheduledDate)->addDays(($i % 5) + 1)->toDateString()
                    : null;

                $price = (int) DB::table('event_prices')
                    ->where('event_id', $event['id'])
                    ->where('participant_type', $participationType)
                    ->value('price');

                if ($price <= 0) {
                    $price = (int) $event['base_price'];
                }

                $discountId = null;
                $discountAmount = 0;
                $discountNote = null;

                if ($participationType === '兄弟参加') {
                    $discountId = $discounts['sibling'];
                    $discountAmount = min(1000, $price);
                    $discountNote = '【Phase0】兄弟参加の確認用割引';
                } elseif ($i % 7 === 0) {
                    $discountId = $discounts['early'];
                    $discountAmount = min(500, $price);
                    $discountNote = '【Phase0】早期申込の確認用割引';
                }

                $amount = max(0, $price - $discountAmount);
                $schoolId = $student->school_id ?: $firstSchoolId;

                $applicationId = DB::table('event_applications')->insertGetId([
                    'event_id' => $event['id'],
                    'event_schedule_id' => $event['schedule_id'],
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'event_price_id' => DB::table('event_prices')
                        ->where('event_id', $event['id'])
                        ->where('participant_type', $participationType)
                        ->value('id'),
                    'applicant_user_id' => $userId,
                    'participant_type' => $participationType,
                    'application_status' => $paymentStatus === 'cancelled' ? 'cancelled' : 'applied',
                    'applied_at' => Carbon::parse($scheduledDate)->subDays(10)->format('Y-m-d 10:00:00'),
                    'cancelled_at' => $paymentStatus === 'cancelled' ? Carbon::parse($scheduledDate)->addDays(2)->format('Y-m-d 12:00:00') : null,
                    'cancelled_reason' => $paymentStatus === 'cancelled' ? '【Phase0】仮想データ：都合によりキャンセル' : null,
                    'memo' => '【Phase0】イベント申込確認用ダミーデータ ' . $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $accountTransactionId = DB::table('account_transactions')->insertGetId([
                    'scheduled_date' => $scheduledDate,
                    'transaction_date' => $paidDate,
                    'account_category_id' => $accountCategoryId,
                    'payment_method_id' => $paymentMethod->id,
                    'transaction_name' => $event['title'],
                    'amount' => $amount,
                    'before_discount_amount' => $price,
                    'discount_amount' => $discountAmount,
                    'discount_type_id' => $discountId,
                    'discount_note' => $discountNote,
                    'status' => match ($paymentStatus) {
                        'paid' => 'confirmed',
                        'cancelled' => 'cancelled',
                        default => 'planned',
                    },
                    'cancelled_reason' => $paymentStatus === 'cancelled' ? '【Phase0】仮想データ：キャンセル確認用' : null,
                    'memo' => '【Phase0】イベント売上確認用ダミーデータ ' . $i,
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'source_table' => 'event_application',
                    'source_id' => $applicationId,
                    'related_transaction_id' => null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('event_payments')->insert([
                    'event_application_id' => $applicationId,
                    'account_transaction_id' => $accountTransactionId,
                    'payment_method_id' => $paymentMethod->id,
                    'scheduled_payment_date' => $scheduledDate,
                    'payment_provider' => 'manual',
                    'payment_method' => $paymentMethod->name,
                    'provider_payment_id' => null,
                    'provider_checkout_id' => null,
                    'provider_customer_id' => null,
                    'amount' => $amount,
                    'currency' => 'JPY',
                    'payment_status' => $paymentStatus,
                    'paid_at' => $paidDate ? Carbon::parse($paidDate)->format('Y-m-d 10:00:00') : null,
                    'failed_at' => null,
                    'cancelled_at' => $paymentStatus === 'cancelled' ? Carbon::parse($scheduledDate)->addDays(2)->format('Y-m-d 12:00:00') : null,
                    'raw_response' => null,
                    'memo' => '【Phase0】イベント入金確認用ダミーデータ ' . $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }


    private function syncPostgresSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'users',
            'event_categories',
            'event_statuses',
            'account_categories',
            'payment_methods',
            'discounts',
            'events',
            'event_schedules',
            'event_prices',
            'event_applications',
            'account_transactions',
            'event_payments',
        ];

        foreach ($tables as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), true)");
        }
    }

    private function deleteOldDummyData(): void
    {
        $transactionIds = DB::table('account_transactions')
            ->where('source_table', 'event_application')
            ->where('memo', 'like', '【Phase0】%')
            ->pluck('id');

        $applicationIds = DB::table('account_transactions')
            ->whereIn('id', $transactionIds)
            ->pluck('source_id');

        if ($transactionIds->isNotEmpty()) {
            DB::table('event_payments')->whereIn('account_transaction_id', $transactionIds)->delete();
            DB::table('account_transactions')->whereIn('id', $transactionIds)->delete();
        }

        if ($applicationIds->isNotEmpty()) {
            DB::table('event_applications')->whereIn('id', $applicationIds)->delete();
        }
    }

    private function ensureUser($now): int
    {
        $userId = DB::table('users')->orderBy('id')->value('id');
        if ($userId) {
            return (int) $userId;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => 'システム管理者',
            'email' => 'system@example.test',
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureEventCategory($now): int
    {
        $id = DB::table('event_categories')->where('name', 'Phase0確認用')->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('event_categories')->insertGetId([
            'name' => 'Phase0確認用',
            'description' => 'イベント売上画面確認用カテゴリ',
            'display_order' => 999,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureEventStatus($now): int
    {
        DB::table('event_statuses')->updateOrInsert(
            ['code' => 'phase0_open'],
            [
                'name' => '募集中',
                'description' => 'Phase0確認用ステータス',
                'display_order' => 999,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('event_statuses')->where('code', 'phase0_open')->value('id');
    }

    private function ensureAccountCategory($now): int
    {
        DB::table('account_categories')->updateOrInsert(
            ['code' => 'event_sales'],
            [
                'name' => 'イベント売上',
                'transaction_type' => '収益',
                'sort_order' => 40,
                'is_active' => true,
                'note' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('account_categories')->where('code', 'event_sales')->value('id');
    }

    private function ensurePaymentMethods($now)
    {
        $methods = [
            ['code' => 'cash', 'name' => '現金', 'is_auto_payment' => false, 'sort_order' => 10],
            ['code' => 'bank_transfer', 'name' => '銀行振込', 'is_auto_payment' => false, 'sort_order' => 20],
            ['code' => 'credit_card', 'name' => 'クレジットカード', 'is_auto_payment' => true, 'sort_order' => 30],
            ['code' => 'paypay', 'name' => 'PayPay決済', 'is_auto_payment' => true, 'sort_order' => 40],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['code' => $method['code']],
                array_merge($method, [
                    'is_active' => true,
                    'note' => 'Phase0確認用に不足時のみ作成',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        return DB::table('payment_methods')
            ->whereIn('code', array_column($methods, 'code'))
            ->orderBy('sort_order')
            ->get()
            ->values();
    }

    private function ensureDiscounts($now): array
    {
        $items = [
            'sibling' => [
                'code' => 'event_sibling_discount',
                'name' => '兄弟割',
                'sort_order' => 10,
                'note' => 'Phase0確認用',
            ],
            'early' => [
                'code' => 'event_early_discount',
                'name' => '早割',
                'sort_order' => 20,
                'note' => 'Phase0確認用',
            ],
        ];

        foreach ($items as $item) {
            DB::table('discounts')->updateOrInsert(
                ['code' => $item['code']],
                array_merge($item, [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        return [
            'sibling' => (int) DB::table('discounts')->where('code', 'event_sibling_discount')->value('id'),
            'early' => (int) DB::table('discounts')->where('code', 'event_early_discount')->value('id'),
        ];
    }

    private function ensureEvents(int $eventCategoryId, int $eventStatusId, int $userId, $now): array
    {
        $definitions = [
            ['title' => 'サマーチャレンジ2025', 'base_price' => 8000, 'month' => 7],
            ['title' => 'キッズ将棋大会', 'base_price' => 3000, 'month' => 8],
            ['title' => '理科実験イベント', 'base_price' => 2500, 'month' => 9],
            ['title' => '秋の親子学習会', 'base_price' => 4500, 'month' => 10],
            ['title' => '冬休み脳開発特訓', 'base_price' => 12000, 'month' => 12],
            ['title' => '春の体験イベント', 'base_price' => 5000, 'month' => 3],
        ];

        $events = [];
        foreach ($definitions as $index => $definition) {
            $event = DB::table('events')->where('title', $definition['title'])->first();

            if ($event) {
                $eventId = (int) $event->id;
                DB::table('events')->where('id', $eventId)->update([
                    'event_category_id' => $eventCategoryId,
                    'event_status_id' => $eventStatusId,
                    'is_paid' => true,
                    'is_external_allowed' => false,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
            } else {
                $eventId = (int) DB::table('events')->insertGetId([
                    'event_category_id' => $eventCategoryId,
                    'event_status_id' => $eventStatusId,
                    'title' => $definition['title'],
                    'description' => 'Phase0 イベント売上画面確認用イベント',
                    'organizer_type' => 'school',
                    'organizer_user_id' => $userId,
                    'is_paid' => true,
                    'is_external_allowed' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $startAt = Carbon::create(2026, $definition['month'], min(20, 5 + $index * 3), 10, 0, 0);
            $schedule = DB::table('event_schedules')
                ->where('event_id', $eventId)
                ->where('start_at', $startAt->format('Y-m-d H:i:s'))
                ->first();

            if ($schedule) {
                $scheduleId = (int) $schedule->id;
            } else {
                $scheduleId = (int) DB::table('event_schedules')->insertGetId([
                    'event_id' => $eventId,
                    'start_at' => $startAt->format('Y-m-d H:i:s'),
                    'end_at' => $startAt->copy()->addHours(2)->format('Y-m-d H:i:s'),
                    'capacity' => 60,
                    'min_participants' => 1,
                    'waitlist_enabled' => true,
                    'waitlist_capacity' => 20,
                    'application_start_at' => $startAt->copy()->subMonths(2)->format('Y-m-d H:i:s'),
                    'application_deadline_at' => $startAt->copy()->subDays(7)->format('Y-m-d H:i:s'),
                    'cancel_deadline_at' => $startAt->copy()->subDays(3)->format('Y-m-d H:i:s'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $prices = [
                ['participant_type' => '通常参加', 'price' => $definition['base_price']],
                ['participant_type' => '兄弟参加', 'price' => max(0, $definition['base_price'] - 500)],
                ['participant_type' => '追加参加', 'price' => $definition['base_price'] + 1000],
            ];

            foreach ($prices as $price) {
                $exists = DB::table('event_prices')
                    ->where('event_id', $eventId)
                    ->where('participant_type', $price['participant_type'])
                    ->first();

                $payload = [
                    'price' => $price['price'],
                    'currency' => 'JPY',
                    'is_free' => false,
                    'updated_at' => $now,
                ];

                if ($exists) {
                    DB::table('event_prices')->where('id', $exists->id)->update($payload);
                } else {
                    DB::table('event_prices')->insert(array_merge($payload, [
                        'event_id' => $eventId,
                        'participant_type' => $price['participant_type'],
                        'created_at' => $now,
                    ]));
                }
            }

            $events[] = [
                'id' => $eventId,
                'title' => $definition['title'],
                'schedule_id' => $scheduleId,
                'base_price' => $definition['base_price'],
            ];
        }

        return $events;
    }
}
