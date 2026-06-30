<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\Discount;
use App\Models\PaymentMethod;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EventSaleController extends Controller
{
    public function index(Request $request)
    {
        $rows = $this->getBaseRows();
        $filteredRows = $this->applyFilters($rows, $request);

        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortedRows = $this->sortRows($filteredRows, $sort, $direction);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 50;

        $sales = new LengthAwarePaginator(
            $sortedRows->forPage($page, $perPage)->values(),
            $sortedRows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $chartPeriod = $request->input('chart_period', '1year');
        if (! in_array($chartPeriod, ['all', '5years', '3years', '1year'], true)) {
            $chartPeriod = '1year';
        }

        return view('admin.account.event-sales.index', [
            'sales' => $sales,
            'summary' => $this->buildSummary($filteredRows),
            'eventSalesChartData' => $this->buildEventSalesChartData($filteredRows, $chartPeriod),
            'eventApplicationChartData' => $this->buildEventApplicationChartData($filteredRows, $chartPeriod),
            'chartPeriod' => $chartPeriod,

            'students' => DB::table('students')
                ->select('id', 'student_code', 'last_name', 'first_name')
                ->orderBy('id')
                ->get(),

            'schools' => School::orderBy('name')->get(),

            'accountCategories' => AccountCategory::where('transaction_type', '収益')
                ->where('name', 'イベント売上')
                ->orderBy('sort_order')
                ->get(),

            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),
            'discounts' => Discount::orderBy('sort_order')->get(),

            'events' => DB::table('events')
                ->select('id', 'title')
                ->where('is_active', true)
                ->orderBy('title')
                ->get(),

            'participationTypes' => $rows->pluck('participation_type')->filter()->unique()->sort()->values(),

            'filters' => $request->only([
                'scheduled_from',
                'scheduled_to',
                'transaction_from',
                'transaction_to',
                'school_id',
                'event_id',
                'participation_type',
                'payment_method_id',
                'discount_type_id',
                'payment_status',
                'keyword',
            ]),

            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->sortRows($this->applyFilters($this->getBaseRows(), $request), 'id', 'desc');
        $filename = 'event_sales_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ID',
                '教室',
                '生徒',
                '生徒コード',
                'イベント名',
                '参加区分',
                '会計区分',
                '取引予定日',
                '入出金方法',
                '取引日',
                '割引種別',
                '割引前金額',
                '割引額',
                '取引額(税込)',
                '入金状態',
                '取引メモ',
                '割引メモ',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->school_name,
                    $row->student_name,
                    $row->student_code,
                    $row->event_title,
                    $row->participation_type,
                    $row->account_category_name,
                    $row->scheduled_date,
                    $row->payment_method_name,
                    $row->transaction_date,
                    $row->discount_type_name,
                    $row->before_discount_amount,
                    $row->discount_amount,
                    $row->amount,
                    $row->payment_status_label,
                    $row->transaction_note,
                    $row->discount_note,
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => ['required', 'exists:students,id'],
            'event_id' => ['required', 'exists:events,id'],
            'event_schedule_id' => ['required', 'exists:event_schedules,id'],
            'event_price_id' => ['nullable', 'exists:event_prices,id'],
            'participation_type' => ['required', 'string', 'max:100'],
            'scheduled_date' => ['required', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payment_status' => ['required', 'in:paid,unpaid,cancelled'],
            'before_discount_amount' => ['required', 'integer', 'min:0'],
            'discount_type_id' => ['nullable', 'exists:discounts,id'],
            'discount_amount' => ['required', 'integer', 'min:0'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'discount_note' => ['nullable', 'string', 'max:1000'],
            'application_memo' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $student = DB::table('students')->where('id', $request->student_id)->first();
            $event = DB::table('events')->where('id', $request->event_id)->first();
            $category = AccountCategory::where('name', 'イベント売上')->firstOrFail();
            $paymentMethod = $request->payment_method_id
                ? DB::table('payment_methods')->where('id', $request->payment_method_id)->first()
                : null;

            $before = (int) $request->before_discount_amount;
            $discount = min((int) $request->discount_amount, $before);
            $total = max(0, $before - $discount);
            $paymentStatus = $request->payment_status;
            $transactionDate = $paymentStatus === 'paid'
                ? ($request->transaction_date ?: now()->toDateString())
                : null;

            $applicationId = DB::table('event_applications')->insertGetId([
                'event_id' => $event->id,
                'event_schedule_id' => $request->event_schedule_id,
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'event_price_id' => $request->event_price_id,
                'applicant_user_id' => auth()->id() ?? 1,
                'participant_type' => $request->participation_type,
                'application_status' => $paymentStatus === 'cancelled' ? 'cancelled' : 'applied',
                'applied_at' => now(),
                'cancelled_at' => $paymentStatus === 'cancelled' ? now() : null,
                'cancelled_reason' => null,
                'memo' => $request->application_memo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $accountTransactionId = DB::table('account_transactions')->insertGetId([
                'scheduled_date' => $request->scheduled_date,
                'transaction_date' => $transactionDate,
                'account_category_id' => $category->id,
                'payment_method_id' => $request->payment_method_id,
                'transaction_name' => $event->title,
                'amount' => $total,
                'before_discount_amount' => $before,
                'discount_amount' => $discount,
                'discount_type_id' => $discount > 0 ? $request->discount_type_id : null,
                'discount_note' => $request->discount_note,
                'status' => match ($paymentStatus) {
                    'paid' => 'confirmed',
                    'cancelled' => 'cancelled',
                    default => 'planned',
                },
                'cancelled_reason' => null,
                'memo' => $request->memo,
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'source_table' => 'event_application',
                'source_id' => $applicationId,
                'related_transaction_id' => null,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('event_payments')->insert([
                'event_application_id' => $applicationId,
                'account_transaction_id' => $accountTransactionId,
                'payment_method_id' => $request->payment_method_id,
                'scheduled_payment_date' => $request->scheduled_date,
                'payment_provider' => 'manual',
                'payment_method' => $paymentMethod->name ?? '未設定',
                'provider_payment_id' => null,
                'provider_checkout_id' => null,
                'provider_customer_id' => null,
                'amount' => $total,
                'currency' => 'JPY',
                'payment_status' => match ($paymentStatus) {
                    'paid' => 'paid',
                    'cancelled' => 'cancelled',
                    default => 'unpaid',
                },
                'paid_at' => $paymentStatus === 'paid' ? Carbon::parse($transactionDate)->format('Y-m-d H:i:s') : null,
                'failed_at' => null,
                'cancelled_at' => $paymentStatus === 'cancelled' ? now() : null,
                'raw_response' => null,
                'memo' => $request->memo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.operations.classroom-accounting.event-sales.index')
            ->with('success', 'イベント売上を登録しました。');
    }

    public function update(Request $request, int $eventApplicationId)
    {
        $validator = Validator::make($request->all(), [
            'scheduled_date' => ['required', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_status' => ['required', 'in:paid,unpaid,cancelled'],
            'before_discount_amount' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['required', 'integer', 'min:0'],
            'discount_type_id' => ['nullable', 'exists:discounts,id'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'discount_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => '入力内容を確認してください。',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request, $eventApplicationId) {
            $application = DB::table('event_applications')->where('id', $eventApplicationId)->first();
            if (! $application) {
                abort(404);
            }

            $accountTransaction = DB::table('account_transactions')
                ->where('source_table', 'event_application')
                ->where('source_id', $eventApplicationId)
                ->first();

            if (! $accountTransaction) {
                abort(404);
            }

            $before = (int) $request->before_discount_amount;
            $discount = min((int) $request->discount_amount, $before);
            $total = max(0, $before - $discount);
            $paymentStatus = $request->payment_status;
            $transactionDate = $paymentStatus === 'paid'
                ? ($request->transaction_date ?: now()->toDateString())
                : null;

            $paymentMethod = $request->payment_method_id
                ? DB::table('payment_methods')->where('id', $request->payment_method_id)->first()
                : null;

            DB::table('account_transactions')
                ->where('id', $accountTransaction->id)
                ->update([
                    'scheduled_date' => $request->scheduled_date,
                    'transaction_date' => $transactionDate,
                    'payment_method_id' => $request->payment_method_id,
                    'amount' => $total,
                    'before_discount_amount' => $before,
                    'discount_amount' => $discount,
                    'discount_type_id' => $discount > 0 ? $request->discount_type_id : null,
                    'discount_note' => $request->discount_note,
                    'status' => match ($paymentStatus) {
                        'paid' => 'confirmed',
                        'cancelled' => 'cancelled',
                        default => 'planned',
                    },
                    'memo' => $request->memo,
                    'updated_by' => auth()->id() ?? 1,
                    'updated_at' => now(),
                ]);

            DB::table('event_applications')
                ->where('id', $eventApplicationId)
                ->update([
                    'application_status' => $paymentStatus === 'cancelled' ? 'cancelled' : 'applied',
                    'cancelled_at' => $paymentStatus === 'cancelled' ? now() : null,
                    'updated_at' => now(),
                ]);

            $paymentPayload = [
                'payment_method_id' => $request->payment_method_id,
                'scheduled_payment_date' => $request->scheduled_date,
                'payment_method' => $paymentMethod->name ?? '未設定',
                'amount' => $total,
                'payment_status' => match ($paymentStatus) {
                    'paid' => 'paid',
                    'cancelled' => 'cancelled',
                    default => 'unpaid',
                },
                'paid_at' => $paymentStatus === 'paid' ? Carbon::parse($transactionDate)->format('Y-m-d H:i:s') : null,
                'cancelled_at' => $paymentStatus === 'cancelled' ? now() : null,
                'memo' => $request->memo,
                'updated_at' => now(),
            ];

            $updatedPaymentCount = DB::table('event_payments')
                ->where('event_application_id', $eventApplicationId)
                ->where('account_transaction_id', $accountTransaction->id)
                ->update($paymentPayload);

            if ($updatedPaymentCount === 0) {
                DB::table('event_payments')->insert(array_merge($paymentPayload, [
                    'event_application_id' => $eventApplicationId,
                    'account_transaction_id' => $accountTransaction->id,
                    'payment_provider' => 'manual',
                    'provider_payment_id' => null,
                    'provider_checkout_id' => null,
                    'provider_customer_id' => null,
                    'currency' => 'JPY',
                    'failed_at' => null,
                    'raw_response' => null,
                    'created_at' => now(),
                ]));
            }
        });

        return response()->json(['message' => '更新しました。']);
    }

    public function searchStudents(Request $request)
    {
        $keyword = trim((string) $request->input('q', ''));
        if (mb_strlen($keyword) < 1) {
            return response()->json([]);
        }

        $students = DB::table('students')
            ->join('schools', 'students.school_id', '=', 'schools.id')
            ->where(function ($query) use ($keyword) {
                $query->where('students.student_code', 'like', "%{$keyword}%")
                    ->orWhere('students.last_name', 'like', "%{$keyword}%")
                    ->orWhere('students.first_name', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(students.last_name, students.first_name) LIKE ?", ["%{$keyword}%"])
                    ->orWhereRaw("CONCAT(students.last_name, ' ', students.first_name) LIKE ?", ["%{$keyword}%"]);
            })
            ->select([
                'students.id',
                'students.student_code',
                'students.last_name',
                'students.first_name',
                'schools.name as school_name',
            ])
            ->orderBy('students.student_code')
            ->limit(20)
            ->get()
            ->map(fn ($student) => [
                'id' => $student->id,
                'label' => $student->student_code . '｜' . $student->last_name . ' ' . $student->first_name,
                'student_code' => $student->student_code,
                'name' => $student->last_name . ' ' . $student->first_name,
                'school_name' => $student->school_name,
            ]);

        return response()->json($students);
    }

    public function searchEvents(Request $request)
    {
        $keyword = trim((string) $request->input('q', ''));
        if (mb_strlen($keyword) < 1) {
            return response()->json([]);
        }

        $events = DB::table('events')
            ->where('events.is_active', true)
            ->where('events.title', 'like', "%{$keyword}%")
            ->select(['events.id', 'events.title'])
            ->orderBy('events.title')
            ->limit(20)
            ->get()
            ->map(function ($event) {
                $schedule = DB::table('event_schedules')
                    ->where('event_id', $event->id)
                    ->orderBy('start_at')
                    ->first();

                $prices = DB::table('event_prices')
                    ->where('event_id', $event->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($price) => [
                        'id' => $price->id,
                        'participant_type' => $price->participant_type ?: '通常参加',
                        'price' => (int) $price->price,
                        'is_free' => (bool) $price->is_free,
                    ]);

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'label' => $event->title,
                    'schedule_id' => $schedule->id ?? null,
                    'schedule_label' => $schedule?->start_at ? Carbon::parse($schedule->start_at)->format('Y/m/d H:i') : '日程未設定',
                    'prices' => $prices,
                    'default_price_id' => $prices->first()['id'] ?? null,
                    'default_participation_type' => $prices->first()['participant_type'] ?? '通常参加',
                    'default_price' => $prices->first()['price'] ?? 0,
                ];
            });

        return response()->json($events);
    }

    private function getBaseRows(): Collection
    {
        return DB::table('account_transactions')
            ->join('account_categories', 'account_transactions.account_category_id', '=', 'account_categories.id')
            ->join('event_applications', function ($join) {
                $join->on('account_transactions.source_id', '=', 'event_applications.id')
                    ->where('account_transactions.source_table', 'event_application');
            })
            ->join('events', 'event_applications.event_id', '=', 'events.id')
            ->leftJoin('event_schedules', 'event_applications.event_schedule_id', '=', 'event_schedules.id')
            ->leftJoin('event_prices', 'event_applications.event_price_id', '=', 'event_prices.id')
            ->leftJoin('students', 'account_transactions.student_id', '=', 'students.id')
            ->leftJoin('schools', 'account_transactions.school_id', '=', 'schools.id')
            ->leftJoin('payment_methods', 'account_transactions.payment_method_id', '=', 'payment_methods.id')
            ->leftJoin('discounts', 'account_transactions.discount_type_id', '=', 'discounts.id')
            ->leftJoin('event_payments', 'event_payments.account_transaction_id', '=', 'account_transactions.id')
            ->leftJoin('users as created_users', 'account_transactions.created_by', '=', 'created_users.id')
            ->leftJoin('users as updated_users', 'account_transactions.updated_by', '=', 'updated_users.id')
            ->where('account_categories.name', 'イベント売上')
            ->select([
                'event_applications.id as id',
                'event_applications.event_id',
                'event_applications.event_schedule_id',
                'event_applications.event_price_id',
                'event_applications.participant_type',
                'event_applications.application_status',
                'event_applications.applied_at',
                'event_applications.cancelled_at',
                'event_applications.cancelled_reason',
                'event_applications.memo as application_memo',
                'events.title as event_title',
                'event_schedules.start_at as event_start_at',
                'event_schedules.end_at as event_end_at',
                'event_prices.price as event_price',
                'account_transactions.id as account_transaction_id',
                'account_transactions.scheduled_date',
                'account_transactions.transaction_date',
                'account_transactions.account_category_id',
                'account_categories.name as account_category_name',
                'account_transactions.payment_method_id',
                'payment_methods.name as payment_method_name',
                'account_transactions.before_discount_amount',
                'account_transactions.discount_amount',
                'account_transactions.discount_type_id',
                'discounts.name as discount_type_name',
                'account_transactions.amount',
                'account_transactions.status',
                'account_transactions.memo as transaction_note',
                'account_transactions.discount_note',
                'account_transactions.school_id',
                'account_transactions.student_id',
                'students.student_code',
                'students.last_name',
                'students.first_name',
                'schools.name as school_name',
                'event_payments.id as event_payment_id',
                'event_payments.payment_status as event_payment_status',
                'account_transactions.created_at',
                'account_transactions.updated_at',
                'created_users.name as created_by_name',
                'updated_users.name as updated_by_name',
            ])
            ->orderByDesc('event_applications.id')
            ->get()
            ->map(function ($row) {
                $before = (int) ($row->before_discount_amount ?? $row->amount ?? 0);
                $discount = (int) ($row->discount_amount ?? 0);
                $amount = (int) ($row->amount ?? max(0, $before - $discount));
                $status = $this->normalizePaymentStatus($row->event_payment_status ?: $row->status);

                return (object) [
                    'id' => (int) $row->id,
                    'account_transaction_id' => (int) $row->account_transaction_id,
                    'event_payment_id' => $row->event_payment_id,
                    'event_id' => (int) $row->event_id,
                    'event_schedule_id' => (int) $row->event_schedule_id,
                    'event_price_id' => $row->event_price_id,
                    'student_id' => $row->student_id,
                    'school_id' => $row->school_id,
                    'payment_method_id' => $row->payment_method_id,
                    'scheduled_date' => $row->scheduled_date,
                    'transaction_date' => $row->transaction_date,
                    'event_title' => $row->event_title ?? '-',
                    'event_start_at' => $row->event_start_at,
                    'event_end_at' => $row->event_end_at,
                    'participation_type' => $row->participant_type ?: '通常参加',
                    'account_category_id' => $row->account_category_id,
                    'account_category_name' => $row->account_category_name ?? 'イベント売上',
                    'school_name' => $row->school_name ?? '-',
                    'student_name' => trim(($row->last_name ?? '') . ' ' . ($row->first_name ?? '')) ?: '-',
                    'student_code' => $row->student_code ?? '-',
                    'discount_type_id' => $row->discount_type_id,
                    'discount_type_name' => $discount > 0 ? ($row->discount_type_name ?? '割引あり') : '-',
                    'before_discount_amount' => $before,
                    'discount_amount' => $discount,
                    'amount' => $amount,
                    'payment_status' => $status,
                    'payment_status_label' => $this->paymentStatusLabel($status),
                    'payment_method_name' => $row->payment_method_name ?? '-',
                    'transaction_note' => $row->transaction_note ?? '-',
                    'discount_note' => $row->discount_note ?? '-',
                    'application_memo' => $row->application_memo ?? '-',
                    'application_status' => $row->application_status ?? '-',
                    'applied_at' => $row->applied_at,
                    'cancelled_at' => $row->cancelled_at,
                    'cancelled_reason' => $row->cancelled_reason ?? '-',
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'created_by_name' => $row->created_by_name ?? '-',
                    'updated_by_name' => $row->updated_by_name ?? '-',
                ];
            });
    }

    private function applyFilters(Collection $rows, Request $request): Collection
    {
        return $rows
            ->when($request->filled('scheduled_from'), fn ($c) => $c->filter(fn ($r) => $r->scheduled_date && $r->scheduled_date >= $request->scheduled_from))
            ->when($request->filled('scheduled_to'), fn ($c) => $c->filter(fn ($r) => $r->scheduled_date && $r->scheduled_date <= $request->scheduled_to))
            ->when($request->filled('transaction_from'), fn ($c) => $c->filter(fn ($r) => $r->transaction_date && $r->transaction_date >= $request->transaction_from))
            ->when($request->filled('transaction_to'), fn ($c) => $c->filter(fn ($r) => $r->transaction_date && $r->transaction_date <= $request->transaction_to))
            ->when($request->filled('school_id'), fn ($c) => $c->where('school_id', (int) $request->school_id))
            ->when($request->filled('event_id'), fn ($c) => $c->where('event_id', (int) $request->event_id))
            ->when($request->filled('participation_type'), fn ($c) => $c->where('participation_type', $request->participation_type))
            ->when($request->filled('payment_method_id'), fn ($c) => $c->where('payment_method_id', (int) $request->payment_method_id))
            ->when($request->filled('discount_type_id'), fn ($c) => $c->where('discount_type_id', (int) $request->discount_type_id))
            ->when($request->filled('payment_status'), fn ($c) => $c->where('payment_status', $request->payment_status))
            ->when($request->filled('keyword'), function ($c) use ($request) {
                $keyword = trim($request->keyword);
                return $c->filter(fn ($r) =>
                    str_contains($r->event_title, $keyword)
                    || str_contains($r->student_name, $keyword)
                    || str_contains($r->student_code, $keyword)
                    || str_contains($r->transaction_note ?? '', $keyword)
                    || str_contains($r->discount_note ?? '', $keyword)
                    || str_contains($r->participation_type ?? '', $keyword)
                );
            })
            ->values();
    }

    private function sortRows(Collection $rows, string $sort, string $direction): Collection
    {
        $keyMap = [
            'id' => 'id',
            'school' => 'school_name',
            'student' => 'student_name',
            'student_code' => 'student_code',
            'event_title' => 'event_title',
            'participation_type' => 'participation_type',
            'account_category' => 'account_category_name',
            'scheduled_date' => 'scheduled_date',
            'payment_method' => 'payment_method_name',
            'transaction_date' => 'transaction_date',
            'discount_amount' => 'discount_amount',
            'before_discount_amount' => 'before_discount_amount',
            'amount' => 'amount',
            'payment_status' => 'payment_status',
        ];

        $key = $keyMap[$sort] ?? 'id';
        return $direction === 'asc'
            ? $rows->sortBy($key, SORT_REGULAR)->values()
            : $rows->sortByDesc($key, SORT_REGULAR)->values();
    }

    private function buildSummary(Collection $rows): array
    {
        $currentMonth = Carbon::now()->format('Y/m');
        $currentRows = $rows->filter(function ($row) use ($currentMonth) {
            $date = $row->transaction_date ?: $row->scheduled_date;
            return $date && Carbon::parse($date)->format('Y/m') === $currentMonth;
        });

        $salesAmount = $currentRows->sum('amount');
        $paidAmount = $currentRows->where('payment_status', 'paid')->sum('amount');
        $unpaidAmount = $currentRows->where('payment_status', 'unpaid')->sum('amount');
        $collectionRate = $salesAmount > 0 ? round(($paidAmount / $salesAmount) * 100, 1) : 0;

        return [
            'sales_amount' => $salesAmount,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $unpaidAmount,
            'collection_rate' => $collectionRate,
        ];
    }

    private function buildEventSalesChartData(Collection $rows, string $period): Collection
    {
        $labels = $this->buildMonthLabels($rows, $period);
        return collect([
            'labels' => $labels,
            'amounts' => $labels->map(function ($label) use ($rows) {
                return $rows->filter(function ($row) use ($label) {
                    $date = $row->transaction_date ?: $row->scheduled_date;
                    return $date && Carbon::parse($date)->format('Y/m') === $label;
                })->sum('amount');
            })->values(),
        ]);
    }

    private function buildEventApplicationChartData(Collection $rows, string $period): Collection
    {
        $labels = $this->buildMonthLabels($rows, $period);
        return collect([
            'labels' => $labels,
            'counts' => $labels->map(function ($label) use ($rows) {
                return $rows->filter(function ($row) use ($label) {
                    $date = $row->applied_at ?: $row->scheduled_date;
                    return $date && Carbon::parse($date)->format('Y/m') === $label;
                })->count();
            })->values(),
        ]);
    }

    private function buildMonthLabels(Collection $rows, string $period): Collection
    {
        $now = Carbon::now()->startOfMonth();
        $start = match ($period) {
            '5years' => $now->copy()->subMonths(59)->startOfMonth(),
            '3years' => $now->copy()->subMonths(35)->startOfMonth(),
            'all' => $rows->pluck('scheduled_date')->filter()->min()
                ? Carbon::parse($rows->pluck('scheduled_date')->filter()->min())->startOfMonth()
                : $now->copy()->subMonths(11)->startOfMonth(),
            default => $now->copy()->subMonths(11)->startOfMonth(),
        };

        return collect(range(0, $start->diffInMonths($now)))
            ->map(fn ($i) => $start->copy()->addMonths($i)->format('Y/m'))
            ->values();
    }

    private function normalizePaymentStatus(?string $status): string
    {
        return match ($status) {
            'paid', 'confirmed', '入金済' => 'paid',
            'cancelled', 'canceled', '取消' => 'cancelled',
            default => 'unpaid',
        };
    }

    private function paymentStatusLabel(?string $status): string
    {
        return match ($this->normalizePaymentStatus($status)) {
            'paid' => '入金済',
            'cancelled' => '取消',
            default => '未入金',
        };
    }
}
