<?php

namespace App\Http\Controllers\Admin\Account;

use App\Enums\RefundSourceType;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        $summaryRows = (clone $query)->get([
            'refunds.refund_amount',
            'refunds.status',
            'refunds.refund_source_type',
            'refunds.scheduled_date',
            'refunds.refunded_at',
        ]);

        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $this->applySort($query, $sort, $direction);

        $refunds = $query->paginate(50)->withQueryString();
        $refunds->getCollection()->transform(fn ($row) => $this->decorateRow($row));

        $chartPeriod = $request->input('chart_period', '1year');
        if (! in_array($chartPeriod, ['all', '5years', '3years', '1year'], true)) {
            $chartPeriod = '1year';
        }

        return view('admin.account.refunds.index', [
            'refunds' => $refunds,
            'totalRefundCount' => Refund::count(),
            'summary' => $this->buildSummary($summaryRows),
            'refundAmountChartData' => $this->buildRefundAmountChartData($summaryRows, $chartPeriod),
            'refundCountChartData' => $this->buildRefundCountChartData($summaryRows, $chartPeriod),
            'refundSourceChartData' => $this->buildRefundSourceChartData($summaryRows),
            'chartPeriod' => $chartPeriod,
            'schools' => School::orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),
            'refundStatuses' => RefundStatus::options(),
            'refundSourceTypes' => RefundSourceType::options(),
            'filters' => $request->only([
                'scheduled_from', 'scheduled_to', 'refunded_from', 'refunded_to', 'school_id',
                'refund_source_type', 'refund_method_id', 'status', 'keyword',
            ]),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);
        $this->applySort($query, 'id', 'desc');
        $rows = $query->get()->map(fn ($row) => $this->decorateRow($row));
        $filename = 'refunds_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['ID', '返金番号', '教室', '生徒', '生徒コード', '返金元区分', '返金対象', '返金予定日', '返金方法', '返金日', '返金額', '状態', '返金理由', 'メモ']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->refund_code,
                    $row->school_name,
                    $row->student_name,
                    $row->student_code,
                    $row->refund_source_type_label,
                    $row->refund_target_label,
                    $row->scheduled_date,
                    $row->refund_method_name,
                    $row->refunded_at,
                    $row->refund_amount,
                    $row->status_label,
                    $row->refund_reason,
                    $row->memo,
                ]);
            }
            fclose($handle);
        }, $filename);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refund_source_type' => ['required', 'in:tuition_enrollment,shop,event,spot,other'],
            'refund_source_id' => ['nullable', 'integer', 'min:1'],
            'student_id' => ['nullable', 'exists:students,id'],
            'refund_amount' => ['required', 'integer', 'min:1'],
            'refund_method_id' => ['nullable', 'exists:payment_methods,id'],
            'scheduled_date' => ['required', 'date'],
            'refunded_at' => ['nullable', 'date'],
            'status' => ['required', 'in:pending,completed,cancelled'],
            'refund_reason' => ['nullable', 'string', 'max:1000'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if ($request->status !== 'cancelled' && ! trim((string) $request->refund_reason)) {
            return back()->withErrors(['refund_reason' => '未返金・返金済の場合は返金理由を入力してください。'])->withInput();
        }

        DB::transaction(function () use ($request) {
            $sourceType = (string) $request->refund_source_type;
            $sourceId = $sourceType === 'other' ? null : (int) $request->refund_source_id;
            $source = $this->resolveSourceForStore($sourceType, $sourceId, (int) $request->student_id);
            $amount = (int) $request->refund_amount;

            abort_if($amount > (int) $source['remaining_amount'], 422, '返金可能額を超えています。');

            $refund = Refund::create([
                'school_id' => $source['school_id'],
                'student_id' => $source['student_id'],
                'refund_code' => $this->generateRefundCode(),
                'refund_source_type' => $sourceType,
                'refund_source_id' => $sourceId,
                'refund_amount' => $amount,
                'refund_method_id' => $request->refund_method_id,
                'refund_reason' => $request->refund_reason,
                'scheduled_date' => $request->scheduled_date,
                'refunded_at' => $request->status === 'completed' ? ($request->refunded_at ?: now()->toDateString()) : null,
                'status' => $request->status,
                'memo' => $request->memo,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]);

            $this->upsertAccountTransaction($refund);
        });

        return redirect()->route('admin.operations.classroom-accounting.refunds.index')->with('success', '返金を登録しました。');
    }

    public function update(Request $request, int $refundId)
    {
        $validator = Validator::make($request->all(), [
            'refund_amount' => ['required', 'integer', 'min:1'],
            'refund_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'scheduled_date' => ['required', 'date'],
            'refunded_at' => ['nullable', 'date'],
            'status' => ['required', 'in:pending,completed,cancelled'],
            'refund_reason' => ['nullable', 'string', 'max:1000'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '入力内容を確認してください。', 'errors' => $validator->errors()], 422);
        }

        if ($request->status !== 'cancelled' && ! trim((string) $request->refund_reason)) {
            return response()->json(['message' => '未返金・返金済の場合は返金理由を入力してください。'], 422);
        }

        DB::transaction(function () use ($request, $refundId) {
            $refund = Refund::findOrFail($refundId);
            $amount = (int) $request->refund_amount;

            if ($refund->refund_source_type !== 'other' && $refund->refund_source_id) {
                $source = $this->resolveSourceForStore($refund->refund_source_type, (int) $refund->refund_source_id, (int) $refund->student_id, $refund->id, false);
                abort_if($amount > (int) $source['remaining_amount'], 422, '返金可能額を超えています。');
            }

            $refund->update([
                'refund_amount' => $amount,
                'refund_method_id' => $request->refund_method_id,
                'scheduled_date' => $request->scheduled_date,
                'refunded_at' => $request->status === 'completed' ? ($request->refunded_at ?: now()->toDateString()) : null,
                'status' => $request->status,
                'refund_reason' => $request->refund_reason,
                'memo' => $request->memo,
                'updated_by' => auth()->id() ?? 1,
            ]);

            $this->upsertAccountTransaction($refund->fresh());
        });

        return response()->json(['message' => '更新しました。']);
    }

    public function searchStudents(Request $request)
    {
        $keyword = trim((string) $request->input('q', ''));
        if ($keyword === '') {
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
            ->select('students.id', 'students.student_code', 'students.last_name', 'students.first_name', 'students.school_id', 'schools.name as school_name')
            ->orderBy('students.student_code')
            ->limit(20)
            ->get()
            ->map(fn ($student) => [
                'id' => $student->id,
                'label' => $student->student_code . '｜' . $student->last_name . ' ' . $student->first_name,
                'student_code' => $student->student_code,
                'name' => $student->last_name . ' ' . $student->first_name,
                'school_id' => $student->school_id,
                'school_name' => $student->school_name,
            ]);

        return response()->json($students);
    }

    public function searchTargets(Request $request)
    {
        $sourceType = (string) $request->input('type', '');
        $keyword = trim((string) $request->input('q', ''));

        if (! in_array($sourceType, ['tuition_enrollment', 'shop', 'event', 'spot'], true)) {
            return response()->json([]);
        }

        $rows = match ($sourceType) {
            'tuition_enrollment' => $this->searchInvoiceTargets($keyword),
            'shop' => $this->searchShopTargets($keyword),
            'event' => $this->searchEventTargets($keyword),
            'spot' => $this->searchSpotTargets($keyword),
        };

        return response()->json($rows);
    }

    private function baseQuery()
    {
        return DB::table('refunds')
            ->join('schools', 'refunds.school_id', '=', 'schools.id')
            ->leftJoin('students', 'refunds.student_id', '=', 'students.id')
            ->leftJoin('payment_methods', 'refunds.refund_method_id', '=', 'payment_methods.id')
            ->leftJoin('users as created_users', 'refunds.created_by', '=', 'created_users.id')
            ->leftJoin('users as updated_users', 'refunds.updated_by', '=', 'updated_users.id')
            ->select([
                'refunds.id',
                'refunds.refund_code',
                'refunds.school_id',
                'schools.name as school_name',
                'refunds.student_id',
                'students.student_code',
                DB::raw("CONCAT(COALESCE(students.last_name, ''), ' ', COALESCE(students.first_name, '')) as student_name"),
                'refunds.refund_source_type',
                'refunds.refund_source_id',
                'refunds.refund_amount',
                'refunds.refund_method_id',
                'payment_methods.name as refund_method_name',
                'refunds.scheduled_date',
                'refunds.refunded_at',
                'refunds.status',
                'refunds.refund_reason',
                'refunds.memo',
                'created_users.name as created_by_name',
                'updated_users.name as updated_by_name',
                'refunds.created_at',
                'refunds.updated_at',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('scheduled_from'), fn ($q) => $q->whereDate('refunds.scheduled_date', '>=', $request->scheduled_from))
            ->when($request->filled('scheduled_to'), fn ($q) => $q->whereDate('refunds.scheduled_date', '<=', $request->scheduled_to))
            ->when($request->filled('refunded_from'), fn ($q) => $q->whereDate('refunds.refunded_at', '>=', $request->refunded_from))
            ->when($request->filled('refunded_to'), fn ($q) => $q->whereDate('refunds.refunded_at', '<=', $request->refunded_to))
            ->when($request->filled('school_id'), fn ($q) => $q->where('refunds.school_id', $request->school_id))
            ->when($request->filled('refund_source_type'), fn ($q) => $q->where('refunds.refund_source_type', $request->refund_source_type))
            ->when($request->filled('refund_method_id'), fn ($q) => $q->where('refunds.refund_method_id', $request->refund_method_id))
            ->when($request->filled('status'), fn ($q) => $q->where('refunds.status', $request->status))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = '%' . $request->keyword . '%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('refunds.refund_code', 'like', $keyword)
                        ->orWhere('refunds.refund_reason', 'like', $keyword)
                        ->orWhere('refunds.memo', 'like', $keyword)
                        ->orWhere('students.student_code', 'like', $keyword)
                        ->orWhere('students.last_name', 'like', $keyword)
                        ->orWhere('students.first_name', 'like', $keyword);
                });
            });
    }

    private function applySort($query, string $sort, string $direction): void
    {
        $map = [
            'id' => 'refunds.id',
            'refund_code' => 'refunds.refund_code',
            'school' => 'schools.name',
            'student' => 'students.last_name',
            'source_type' => 'refunds.refund_source_type',
            'scheduled_date' => 'refunds.scheduled_date',
            'refund_method' => 'payment_methods.name',
            'refunded_at' => 'refunds.refunded_at',
            'amount' => 'refunds.refund_amount',
            'status' => 'refunds.status',
        ];

        $query->orderBy($map[$sort] ?? 'refunds.id', $direction);
    }

    private function decorateRow($row)
    {
        $row->student_name = trim((string) $row->student_name) ?: '-';
        $row->student_code = $row->student_code ?: '-';
        $row->refund_method_name = $row->refund_method_name ?: '未設定';
        $row->refund_source_type_label = $this->sourceTypeLabel($row->refund_source_type);
        $row->refund_target_label = $this->resolveSourceLabel($row->refund_source_type, $row->refund_source_id);
        $row->status_label = $this->statusLabel($row->status);
        $row->status_class = $this->statusClass($row->status);
        $row->scheduled_date = $row->scheduled_date ? Carbon::parse($row->scheduled_date)->toDateString() : null;
        $row->refunded_at = $row->refunded_at ? Carbon::parse($row->refunded_at)->toDateString() : null;
        $row->refund_reason = $row->refund_reason ?: '-';
        $row->memo = $row->memo ?: '-';

        return $row;
    }

    private function buildSummary($rows): array
    {
        $now = Carbon::now();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();
        $lastYearSameMonth = $now->copy()->subYear()->startOfMonth();

        $monthRows = function ($month) use ($rows) {
            return $rows->filter(function ($row) use ($month) {
                $date = $row->refunded_at ?: $row->scheduled_date;
                return $date && Carbon::parse($date)->isSameMonth($month);
            });
        };

        $rate = function ($targetRows): float {
            $validCount = $targetRows->whereIn('status', ['pending', 'completed'])->count();
            if ($validCount <= 0) {
                return 0.0;
            }

            return round($targetRows->where('status', 'completed')->count() / $validCount * 100, 1);
        };

        $changeRate = function ($current, $previous): ?float {
            $current = (float) $current;
            $previous = (float) $previous;
            if ($previous == 0.0) {
                return $current == 0.0 ? 0.0 : null;
            }

            return round(($current - $previous) / $previous * 100, 1);
        };

        $thisMonthRows = $monthRows($thisMonth);
        $lastMonthRows = $monthRows($lastMonth);
        $lastYearRows = $monthRows($lastYearSameMonth);
        $validCount = max(1, $rows->whereIn('status', ['pending', 'completed'])->count());
        $completedCount = $rows->where('status', 'completed')->count();

        $thisMonthAmount = (int) $thisMonthRows->sum('refund_amount');
        $lastYearAmount = (int) $lastYearRows->sum('refund_amount');
        $thisMonthPendingAmount = (int) $thisMonthRows->where('status', 'pending')->sum('refund_amount');
        $lastYearPendingAmount = (int) $lastYearRows->where('status', 'pending')->sum('refund_amount');
        $thisMonthCount = $thisMonthRows->count();
        $lastYearCount = $lastYearRows->count();
        $thisMonthRate = $rate($thisMonthRows);
        $lastYearRate = $rate($lastYearRows);

        return [
            'count' => $rows->count(),
            'total_amount' => (int) $rows->sum('refund_amount'),
            'completed_amount' => (int) $rows->where('status', 'completed')->sum('refund_amount'),
            'pending_amount' => (int) $rows->where('status', 'pending')->sum('refund_amount'),
            'completed_count' => $completedCount,
            'completion_rate' => round($completedCount / $validCount * 100, 1),

            'this_month_amount' => $thisMonthAmount,
            'last_month_amount' => (int) $lastMonthRows->sum('refund_amount'),
            'last_year_amount' => $lastYearAmount,
            'amount_yoy_rate' => $changeRate($thisMonthAmount, $lastYearAmount),

            'this_month_pending_amount' => $thisMonthPendingAmount,
            'last_month_pending_amount' => (int) $lastMonthRows->where('status', 'pending')->sum('refund_amount'),
            'last_year_pending_amount' => $lastYearPendingAmount,
            'pending_amount_yoy_rate' => $changeRate($thisMonthPendingAmount, $lastYearPendingAmount),

            'this_month_count' => $thisMonthCount,
            'last_month_count' => $lastMonthRows->count(),
            'last_year_count' => $lastYearCount,
            'count_yoy_rate' => $changeRate($thisMonthCount, $lastYearCount),

            'this_month_completion_rate' => $thisMonthRate,
            'last_month_completion_rate' => $rate($lastMonthRows),
            'last_year_completion_rate' => $lastYearRate,
            'completion_rate_yoy_rate' => $lastYearRate == 0.0 ? ($thisMonthRate == 0.0 ? 0.0 : null) : round($thisMonthRate - $lastYearRate, 1),
        ];
    }

    private function buildRefundAmountChartData($rows, string $period): array
    {
        $labels = $this->buildMonthLabels($rows, $period);
        return $labels->map(function ($label) use ($rows) {
            $amount = $rows->filter(function ($row) use ($label) {
                $date = $row->refunded_at ?: $row->scheduled_date;
                return $date && Carbon::parse($date)->format('Y/m') === $label;
            })->sum('refund_amount');
            return ['month' => $label, 'amount' => (int) $amount];
        })->values()->all();
    }

    private function buildRefundCountChartData($rows, string $period): array
    {
        $labels = $this->buildMonthLabels($rows, $period);
        return $labels->map(function ($label) use ($rows) {
            $count = $rows->filter(function ($row) use ($label) {
                $date = $row->refunded_at ?: $row->scheduled_date;
                return $date && Carbon::parse($date)->format('Y/m') === $label;
            })->count();
            return ['month' => $label, 'count' => (int) $count];
        })->values()->all();
    }

    private function buildRefundSourceChartData($rows): array
    {
        return $rows->groupBy(fn ($row) => $this->sourceTypeLabel($row->refund_source_type))->map(fn ($items, $label) => [
            'label' => $label,
            'amount' => (int) $items->sum('refund_amount'),
            'count' => $items->count(),
        ])->values()->all();
    }

    private function buildMonthLabels($rows, string $period)
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

        return collect(range(0, $start->diffInMonths($now)))->map(fn ($i) => $start->copy()->addMonths($i)->format('Y/m'))->values();
    }

    private function searchInvoiceTargets(string $keyword)
    {
        return DB::table('invoices')
            ->leftJoin('students', 'invoices.student_id', '=', 'students.id')
            ->select('invoices.id', 'invoices.student_id', 'students.school_id', 'invoices.invoice_no as code', 'invoices.total_amount as amount', DB::raw('COALESCE(invoices.paid_at, invoices.issue_date) as transaction_date'), 'students.student_code', 'students.last_name', 'students.first_name')
            ->where('invoices.total_amount', '>', 0)
            ->where(function ($query) {
                $query->where('invoices.payment_status', 'paid')
                    ->orWhere('invoices.payment_status', 'completed')
                    ->orWhereNotNull('invoices.paid_at');
            })
            ->when($keyword !== '', fn ($q) => $this->applyTargetKeyword($q, $keyword, 'invoices', 'invoice_no'))
            ->orderByDesc('invoices.id')
            ->limit(30)
            ->get()
            ->map(fn ($row) => $this->targetArray('tuition_enrollment', $row->id, $row->student_id, $row->school_id, $row->amount, $row->code ?: '請求ID:' . $row->id, '授業料・入会金', $this->studentLabel($row), $row->transaction_date))
            ->values();
    }

    private function searchShopTargets(string $keyword)
    {
        return DB::table('shop_orders')
            ->leftJoin('students', 'shop_orders.student_id', '=', 'students.id')
            ->select('shop_orders.id', 'shop_orders.student_id', 'shop_orders.school_id', 'shop_orders.order_no as code', 'shop_orders.total_amount as amount', DB::raw('COALESCE(shop_orders.transaction_date, shop_orders.ordered_at) as transaction_date'), 'students.student_code', 'students.last_name', 'students.first_name')
            ->where('shop_orders.total_amount', '>', 0)
            ->where(function ($query) {
                $query->where('shop_orders.payment_status', 'paid')
                    ->orWhere('shop_orders.payment_status', 'completed')
                    ->orWhereNotNull('shop_orders.transaction_date');
            })
            ->when($keyword !== '', fn ($q) => $this->applyTargetKeyword($q, $keyword, 'shop_orders', 'order_no'))
            ->orderByDesc('shop_orders.id')
            ->limit(30)
            ->get()
            ->map(fn ($row) => $this->targetArray('shop', $row->id, $row->student_id, $row->school_id, $row->amount, $row->code ?: '注文ID:' . $row->id, 'ショップ注文', $this->studentLabel($row), $row->transaction_date))
            ->values();
    }

    private function searchEventTargets(string $keyword)
    {
        return DB::table('event_applications')
            ->leftJoin('events', 'event_applications.event_id', '=', 'events.id')
            ->leftJoin('event_prices', 'event_applications.event_price_id', '=', 'event_prices.id')
            ->leftJoin('students', 'event_applications.student_id', '=', 'students.id')
            ->where('event_prices.price', '>', 0)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('event_payments')
                    ->whereColumn('event_payments.event_application_id', 'event_applications.id')
                    ->where(function ($sub) {
                        $sub->where('event_payments.payment_status', 'paid')
                            ->orWhere('event_payments.payment_status', 'completed')
                            ->orWhereNotNull('event_payments.paid_at');
                    });
            })
            ->when($keyword !== '', function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->where(function ($sub) use ($like) {
                    $sub->whereRaw('CAST(event_applications.id AS TEXT) LIKE ?', [$like])
                        ->orWhere('events.title', 'like', $like)
                        ->orWhere('students.student_code', 'like', $like)
                        ->orWhere('students.last_name', 'like', $like)
                        ->orWhere('students.first_name', 'like', $like)
                        ->orWhereRaw("CONCAT(students.last_name, students.first_name) LIKE ?", [$like])
                        ->orWhereRaw("CONCAT(students.last_name, ' ', students.first_name) LIKE ?", [$like]);
                });
            })
            ->select('event_applications.id', 'event_applications.student_id', 'event_applications.school_id', 'events.title as title', 'event_prices.price as amount', 'event_applications.applied_at as transaction_date', 'students.student_code', 'students.last_name', 'students.first_name')
            ->orderByDesc('event_applications.id')
            ->limit(30)
            ->get()
            ->map(fn ($row) => $this->targetArray('event', $row->id, $row->student_id, $row->school_id, $row->amount, 'イベント申込ID:' . $row->id, $row->title ?: 'イベント', $this->studentLabel($row), $row->transaction_date))
            ->values();
    }

    private function searchSpotTargets(string $keyword)
    {
        return DB::table('spot_sales')
            ->leftJoin('students', 'spot_sales.student_id', '=', 'students.id')
            ->select('spot_sales.id', 'spot_sales.student_id', 'spot_sales.school_id', 'spot_sales.sale_code as code', 'spot_sales.sale_title as title', 'spot_sales.total_amount as amount', DB::raw('COALESCE(spot_sales.paid_at, spot_sales.sale_date) as transaction_date'), 'students.student_code', 'students.last_name', 'students.first_name')
            ->where('spot_sales.total_amount', '>', 0)
            ->where(function ($query) {
                $query->where('spot_sales.payment_status', 'paid')
                    ->orWhere('spot_sales.payment_status', 'completed')
                    ->orWhereNotNull('spot_sales.paid_at');
            })
            ->when($keyword !== '', fn ($q) => $this->applyTargetKeyword($q, $keyword, 'spot_sales', 'sale_code', 'sale_title'))
            ->orderByDesc('spot_sales.id')
            ->limit(30)
            ->get()
            ->map(fn ($row) => $this->targetArray('spot', $row->id, $row->student_id, $row->school_id, $row->amount, $row->code ?: 'スポットID:' . $row->id, $row->title ?: 'スポット売上', $this->studentLabel($row), $row->transaction_date))
            ->values();
    }

    private function applyTargetKeyword($query, string $keyword, string $table, string $codeColumn, ?string $titleColumn = null): void
    {
        $like = '%' . $keyword . '%';
        $query->where(function ($sub) use ($like, $table, $codeColumn, $titleColumn) {
            $sub->whereRaw("CAST({$table}.id AS TEXT) LIKE ?", [$like])
                ->orWhere("{$table}.{$codeColumn}", 'like', $like)
                ->orWhere('students.student_code', 'like', $like)
                ->orWhere('students.last_name', 'like', $like)
                ->orWhere('students.first_name', 'like', $like)
                ->orWhereRaw("CONCAT(students.last_name, students.first_name) LIKE ?", [$like])
                ->orWhereRaw("CONCAT(students.last_name, ' ', students.first_name) LIKE ?", [$like]);

            if ($titleColumn) {
                $sub->orWhere("{$table}.{$titleColumn}", 'like', $like);
            }
        });
    }

    private function targetArray(string $type, int $id, ?int $studentId, ?int $schoolId, int $amount, string $code, string $title, string $studentLabel, $transactionDate = null): array
    {
        $alreadyRefundedAmount = $this->alreadyRefundedAmount($type, $id);
        $maxAmount = max(0, $amount - $alreadyRefundedAmount);
        $dateText = $transactionDate ? Carbon::parse($transactionDate)->format('Y/m/d') : '-';

        return [
            'type' => $type,
            'id' => $id,
            'student_id' => $studentId,
            'school_id' => $schoolId,
            'original_amount' => $amount,
            'already_refunded_amount' => $alreadyRefundedAmount,
            'max_amount' => $maxAmount,
            'code' => $code,
            'title' => $title,
            'student_label' => $studentLabel,
            'transaction_date' => $dateText,
            'label' => $code . '｜' . $title . '｜' . $studentLabel . '｜¥' . number_format($amount) . '｜返金可能 ¥' . number_format($maxAmount),
        ];
    }

    private function resolveSourceForStore(string $sourceType, ?int $sourceId, int $studentId = 0, ?int $exceptRefundId = null, bool $paidOnly = true): array
    {
        if ($sourceType === 'other') {
            $student = DB::table('students')->where('id', $studentId)->first();
            abort_if(! $student, 422, 'その他返金は生徒を選択してください。');
            return ['school_id' => (int) $student->school_id, 'student_id' => (int) $student->id, 'remaining_amount' => 9999999];
        }

        $meta = $this->sourceMeta($sourceType, $sourceId, $paidOnly);
        abort_if(! $meta, 422, '入金済の返金対象を選択してください。');

        $already = $this->alreadyRefundedAmount($sourceType, $sourceId, $exceptRefundId);
        $remaining = max(0, (int) $meta['amount'] - $already);
        abort_if($remaining <= 0, 422, '返金可能額がありません。');

        return [
            'school_id' => (int) $meta['school_id'],
            'student_id' => (int) $meta['student_id'],
            'remaining_amount' => $remaining,
        ];
    }

    private function resolveSourceLabel(?string $sourceType, ?int $sourceId): string
    {
        if (! $sourceType || $sourceType === 'other' || ! $sourceId) {
            return '手動登録';
        }

        $meta = $this->sourceMeta($sourceType, $sourceId);
        if (! $meta) {
            return $this->sourceTypeLabel($sourceType) . ' ID:' . $sourceId;
        }

        return $meta['code'] . '｜' . $meta['title'];
    }

    private function sourceMeta(?string $sourceType, ?int $sourceId, bool $paidOnly = false): ?array
    {
        if (! $sourceType || ! $sourceId) {
            return null;
        }

        $row = match ($sourceType) {
            'tuition_enrollment' => tap(DB::table('invoices')
                ->leftJoin('students', 'invoices.student_id', '=', 'students.id')
                ->where('invoices.id', $sourceId)
                ->select('invoices.id', 'invoices.student_id', 'students.school_id', 'invoices.invoice_no as code', 'invoices.total_amount as amount'), function ($query) use ($paidOnly) {
                    if ($paidOnly) {
                        $query->where(function ($q) {
                            $q->where('invoices.payment_status', 'paid')->orWhere('invoices.payment_status', 'completed')->orWhereNotNull('invoices.paid_at');
                        });
                    }
                })->first(),
            'shop' => tap(DB::table('shop_orders')
                ->where('id', $sourceId)
                ->select('id', 'student_id', 'school_id', 'order_no as code', 'total_amount as amount'), function ($query) use ($paidOnly) {
                    if ($paidOnly) {
                        $query->where(function ($q) {
                            $q->where('payment_status', 'paid')->orWhere('payment_status', 'completed')->orWhereNotNull('transaction_date');
                        });
                    }
                })->first(),
            'event' => tap(DB::table('event_applications')
                ->leftJoin('events', 'event_applications.event_id', '=', 'events.id')
                ->leftJoin('event_prices', 'event_applications.event_price_id', '=', 'event_prices.id')
                ->where('event_applications.id', $sourceId)
                ->select('event_applications.id', 'event_applications.student_id', 'event_applications.school_id', DB::raw("'イベント申込ID:' || event_applications.id as code"), 'events.title as title', 'event_prices.price as amount'), function ($query) use ($paidOnly) {
                    if ($paidOnly) {
                        $query->whereExists(function ($q) {
                            $q->select(DB::raw(1))->from('event_payments')
                                ->whereColumn('event_payments.event_application_id', 'event_applications.id')
                                ->where(function ($sub) {
                                    $sub->where('event_payments.payment_status', 'paid')->orWhere('event_payments.payment_status', 'completed')->orWhereNotNull('event_payments.paid_at');
                                });
                        });
                    }
                })->first(),
            'spot' => tap(DB::table('spot_sales')
                ->where('id', $sourceId)
                ->select('id', 'student_id', 'school_id', 'sale_code as code', 'sale_title as title', 'total_amount as amount'), function ($query) use ($paidOnly) {
                    if ($paidOnly) {
                        $query->where(function ($q) {
                            $q->where('payment_status', 'paid')->orWhere('payment_status', 'completed')->orWhereNotNull('paid_at');
                        });
                    }
                })->first(),
            default => null,
        };

        if (! $row) {
            return null;
        }

        return [
            'student_id' => (int) $row->student_id,
            'school_id' => (int) $row->school_id,
            'code' => $row->code ?: 'ID:' . $sourceId,
            'title' => $row->title ?? $this->sourceTypeLabel($sourceType),
            'amount' => (int) $row->amount,
        ];
    }

    private function alreadyRefundedAmount(string $sourceType, ?int $sourceId, ?int $exceptRefundId = null): int
    {
        if (! $sourceId || $sourceType === 'other') {
            return 0;
        }

        $query = Refund::where('refund_source_type', $sourceType)
            ->where('refund_source_id', $sourceId)
            ->where('status', '!=', 'cancelled');

        if ($exceptRefundId) {
            $query->where('id', '!=', $exceptRefundId);
        }

        return (int) $query->sum('refund_amount');
    }

    private function upsertAccountTransaction(Refund $refund): void
    {
        $category = AccountCategory::where('name', '返金')->first();
        if (! $category) {
            return;
        }

        AccountTransaction::updateOrCreate(
            ['source_table' => 'refund', 'source_id' => $refund->id],
            [
                'scheduled_date' => $refund->scheduled_date,
                'transaction_date' => $refund->status === 'completed' ? $refund->refunded_at : null,
                'account_category_id' => $category->id,
                'payment_method_id' => $refund->refund_method_id,
                'transaction_name' => $this->sourceTypeLabel($refund->refund_source_type),
                'amount' => $refund->refund_amount,
                'before_discount_amount' => $refund->refund_amount,
                'discount_amount' => 0,
                'discount_type_id' => null,
                'discount_note' => null,
                'status' => $this->toTransactionStatus($refund->status),
                'cancelled_reason' => $refund->status === 'cancelled' ? $refund->refund_reason : null,
                'memo' => trim(($refund->refund_reason ?: '') . "\n" . ($refund->memo ?: '')) ?: null,
                'school_id' => $refund->school_id,
                'student_id' => $refund->student_id,
                'created_by' => auth()->id() ?? 1,
            ]
        );
    }

    private function generateRefundCode(): string
    {
        $prefix = 'REF-' . now()->format('Ymd') . '-';
        $next = Refund::where('refund_code', 'like', $prefix . '%')->count() + 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function studentLabel($row): string
    {
        return trim(($row->student_code ?: '') . ' ' . ($row->last_name ?: '') . ' ' . ($row->first_name ?: '')) ?: '生徒未設定';
    }

    private function sourceTypeLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'tuition_enrollment' => '授業料・入会金返金',
            'shop' => 'ショップ返金',
            'event' => 'イベント返金',
            'spot' => 'スポット返金',
            default => 'その他',
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'completed' => '返金済',
            'cancelled' => '取消',
            default => '未返金',
        };
    }

    private function statusClass(?string $status): string
    {
        return match ($status) {
            'completed' => 'paid',
            'cancelled' => 'cancelled',
            default => 'unpaid',
        };
    }

    private function toTransactionStatus(?string $status): string
    {
        return match ($status) {
            'completed' => 'confirmed',
            'cancelled' => 'cancelled',
            default => 'planned',
        };
    }
}
