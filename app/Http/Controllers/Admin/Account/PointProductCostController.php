<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PointProductCostController extends Controller
{
    private const ACCOUNT_CATEGORY_CODE = 'point_item_cost';
    private const ACCOUNT_SOURCE_TABLE = 'point_exchange';
    private const LEGACY_ACCOUNT_SOURCE_TABLE = 'reward_exchange_requests';

    public function index(Request $request)
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        $summaryRows = (clone $query)->get([
            'reward_exchange_requests.id',
            'reward_exchange_requests.status',
            'reward_exchange_requests.request_points',
            'reward_exchange_requests.requested_at',
            'reward_exchange_requests.delivered_at',
            'reward_items.cost_price',
            'reward_categories.name as reward_category_name',
            'account_transactions.id as account_transaction_id',
        ])->map(fn ($row) => $this->decorateRow($row));

        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $this->applySort($query, $sort, $direction);

        $costs = $query->paginate(50)->withQueryString();
        $costs->getCollection()->transform(fn ($row) => $this->decorateRow($row));

        $chartPeriod = $request->input('chart_period', '1year');
        if (! in_array($chartPeriod, ['all', '5years', '3years', '1year'], true)) {
            $chartPeriod = '1year';
        }

        return view('admin.account.point-product-costs.index', [
            'costs' => $costs,
            'totalCostCount' => DB::table('reward_exchange_requests')->count(),
            'summary' => $this->buildSummary($summaryRows),
            'costAmountChartData' => $this->buildCostAmountChartData($summaryRows, $chartPeriod),
            'costCountChartData' => $this->buildCostCountChartData($summaryRows, $chartPeriod),
            'categoryChartData' => $this->buildCategoryChartData($summaryRows),
            'chartPeriod' => $chartPeriod,
            'schools' => School::orderBy('name')->get(),
            'rewardCategories' => DB::table('reward_categories')->where('is_active', true)->orderBy('sort_order')->get(),
            'rewardItems' => DB::table('reward_items')->where('is_active', true)->orderBy('sort_order')->get(),
            'statuses' => $this->statusOptions(),
            'filters' => $request->only([
                'requested_from', 'requested_to', 'delivered_from', 'delivered_to', 'school_id',
                'reward_category_id', 'reward_item_id', 'status', 'account_sync_status', 'keyword',
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
        $filename = 'point_product_costs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['ID', '教室', '生徒', '生徒コード', 'カテゴリ', '商品名', '使用ポイント', '商品原価', '状態', '申請日', '承認日', '受け渡し日', '却下日', '会計反映', 'メモ']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->school_name,
                    $row->student_name,
                    $row->student_code,
                    $row->reward_category_name,
                    $row->reward_item_name,
                    $row->request_points,
                    $row->cost_price,
                    $row->status_label,
                    $row->requested_at,
                    $row->approved_at,
                    $row->delivered_at,
                    $row->rejected_at,
                    $row->account_sync_label,
                    $row->note,
                ]);
            }
            fclose($handle);
        }, $filename);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => ['required', 'exists:students,id'],
            'reward_item_id' => ['required', 'exists:reward_items,id'],
            'status' => ['required', 'in:requested,approved,delivered,rejected'],
            'requested_at' => ['required', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $item = DB::table('reward_items')->where('id', $request->reward_item_id)->first();
            abort_if(! $item, 422, '商品が見つかりません。');

            $status = (string) $request->status;
            $requestedAt = Carbon::parse($request->requested_at);
            $deliveredAt = $status === 'delivered'
                ? Carbon::parse($request->delivered_at ?: now()->toDateString())
                : null;
            $now = now();

            $exchangeRequestId = DB::table('reward_exchange_requests')->insertGetId([
                'student_id' => $request->student_id,
                'reward_item_id' => $item->id,
                'request_points' => (int) $item->required_points,
                'status' => $status,
                'status_name' => $this->statusOptions()[$status],
                'requested_at' => $requestedAt,
                'approved_at' => in_array($status, ['approved', 'delivered'], true) ? $now : null,
                'delivered_at' => $deliveredAt,
                'rejected_at' => $status === 'rejected' ? $now : null,
                'handled_by' => auth()->id() ?? 1,
                'note' => $request->note,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insertPointTransaction($exchangeRequestId, (int) $request->student_id, $item, $requestedAt);
            $this->syncAccountTransaction($exchangeRequestId);
        });

        return redirect()->route('admin.operations.classroom-accounting.point-product-costs.index')->with('success', 'ポイント商品費用を登録しました。');
    }

    public function update(Request $request, int $invoiceItemId)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:requested,approved,delivered,rejected'],
            'requested_at' => ['required', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '入力内容を確認してください。', 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $invoiceItemId) {
            $exchange = DB::table('reward_exchange_requests')->where('id', $invoiceItemId)->first();
            abort_if(! $exchange, 404, 'ポイント商品交換申請が見つかりません。');

            $status = (string) $request->status;
            $requestedAt = Carbon::parse($request->requested_at);
            $deliveredAt = $status === 'delivered'
                ? Carbon::parse($request->delivered_at ?: now()->toDateString())
                : null;
            $now = now();

            DB::table('reward_exchange_requests')
                ->where('id', $invoiceItemId)
                ->update([
                    'status' => $status,
                    'status_name' => $this->statusOptions()[$status],
                    'requested_at' => $requestedAt,
                    'approved_at' => in_array($status, ['approved', 'delivered'], true) ? ($exchange->approved_at ?: $now) : null,
                    'delivered_at' => $deliveredAt,
                    'rejected_at' => $status === 'rejected' ? ($exchange->rejected_at ?: $now) : null,
                    'handled_by' => auth()->id() ?? 1,
                    'note' => $request->note,
                    'updated_at' => $now,
                ]);

            $this->syncAccountTransaction($invoiceItemId);
        });

        return response()->json(['message' => '更新しました。']);
    }

    public function searchStudents(Request $request)
    {
        $keyword = trim((string) $request->input('q', $request->input('keyword', '')));
        if ($keyword === '') {
            return response()->json([]);
        }

        $students = DB::table('students')
            ->join('schools', 'students.school_id', '=', 'schools.id')
            ->leftJoin('student_point_balances', 'students.id', '=', 'student_point_balances.student_id')
            ->where(function ($query) use ($keyword) {
                $query->where('students.student_code', 'like', "%{$keyword}%")
                    ->orWhere('students.last_name', 'like', "%{$keyword}%")
                    ->orWhere('students.first_name', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(students.last_name, students.first_name) LIKE ?", ["%{$keyword}%"])
                    ->orWhereRaw("CONCAT(students.last_name, ' ', students.first_name) LIKE ?", ["%{$keyword}%"]);
            })
            ->select('students.id', 'students.student_code', 'students.last_name', 'students.first_name', 'students.school_id', 'schools.name as school_name', 'student_point_balances.current_points', 'student_point_balances.total_earned_points', 'student_point_balances.total_used_points')
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
                'current_points' => (int) ($student->current_points ?? 0),
                'total_earned_points' => (int) ($student->total_earned_points ?? 0),
                'total_used_points' => (int) ($student->total_used_points ?? 0),
            ]);

        return response()->json($students);
    }

    private function baseQuery()
    {
        return DB::table('reward_exchange_requests')
            ->join('students', 'reward_exchange_requests.student_id', '=', 'students.id')
            ->leftJoin('schools', 'students.school_id', '=', 'schools.id')
            ->join('reward_items', 'reward_exchange_requests.reward_item_id', '=', 'reward_items.id')
            ->leftJoin('reward_categories', 'reward_items.reward_category_id', '=', 'reward_categories.id')
            ->leftJoin('account_transactions', function ($join) {
                $join->on('account_transactions.source_id', '=', 'reward_exchange_requests.id')
                    ->whereIn('account_transactions.source_table', [self::ACCOUNT_SOURCE_TABLE, self::LEGACY_ACCOUNT_SOURCE_TABLE]);
            })
            ->select([
                'reward_exchange_requests.id',
                'reward_exchange_requests.student_id',
                'reward_exchange_requests.reward_item_id',
                'reward_exchange_requests.request_points',
                'reward_exchange_requests.status',
                'reward_exchange_requests.status_name',
                'reward_exchange_requests.requested_at',
                'reward_exchange_requests.approved_at',
                'reward_exchange_requests.delivered_at',
                'reward_exchange_requests.rejected_at',
                'reward_exchange_requests.note',
                'reward_exchange_requests.created_at',
                'reward_exchange_requests.updated_at',
                'students.student_code',
                'students.last_name',
                'students.first_name',
                'students.school_id',
                'schools.name as school_name',
                'reward_items.name as reward_item_name',
                'reward_items.required_points',
                'reward_items.cost_price',
                'reward_categories.id as reward_category_id',
                'reward_categories.name as reward_category_name',
                'account_transactions.id as account_transaction_id',
                'account_transactions.transaction_date as account_transaction_date',
                'account_transactions.amount as account_amount',
                'account_transactions.created_at as account_created_at',
                'account_transactions.updated_at as account_updated_at',
            ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('requested_from')) {
            $query->whereDate('reward_exchange_requests.requested_at', '>=', $request->requested_from);
        }
        if ($request->filled('requested_to')) {
            $query->whereDate('reward_exchange_requests.requested_at', '<=', $request->requested_to);
        }
        if ($request->filled('delivered_from')) {
            $query->whereDate('reward_exchange_requests.delivered_at', '>=', $request->delivered_from);
        }
        if ($request->filled('delivered_to')) {
            $query->whereDate('reward_exchange_requests.delivered_at', '<=', $request->delivered_to);
        }
        if ($request->filled('school_id')) {
            $query->where('students.school_id', $request->school_id);
        }
        if ($request->filled('reward_category_id')) {
            $query->where('reward_categories.id', $request->reward_category_id);
        }
        if ($request->filled('reward_item_id')) {
            $query->where('reward_items.id', $request->reward_item_id);
        }
        if ($request->filled('status')) {
            $query->where('reward_exchange_requests.status', $request->status);
        }
        if ($request->filled('account_sync_status')) {
            if ($request->account_sync_status === 'synced') {
                $query->whereNotNull('account_transactions.id');
            } elseif ($request->account_sync_status === 'unsynced') {
                $query->whereNull('account_transactions.id');
            }
        }
        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->keyword);
            $query->where(function ($q) use ($keyword) {
                $q->where('students.student_code', 'like', "%{$keyword}%")
                    ->orWhere('students.last_name', 'like', "%{$keyword}%")
                    ->orWhere('students.first_name', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(students.last_name, students.first_name) LIKE ?", ["%{$keyword}%"])
                    ->orWhere('reward_items.name', 'like', "%{$keyword}%")
                    ->orWhere('reward_categories.name', 'like', "%{$keyword}%")
                    ->orWhere('reward_exchange_requests.note', 'like', "%{$keyword}%");
            });
        }
    }

    private function applySort($query, string $sort, string $direction): void
    {
        $map = [
            'id' => 'reward_exchange_requests.id',
            'school' => 'schools.name',
            'student' => 'students.last_name',
            'student_code' => 'students.student_code',
            'category' => 'reward_categories.name',
            'item' => 'reward_items.name',
            'points' => 'reward_exchange_requests.request_points',
            'cost' => 'reward_items.cost_price',
            'status' => 'reward_exchange_requests.status',
            'requested_at' => 'reward_exchange_requests.requested_at',
            'delivered_at' => 'reward_exchange_requests.delivered_at',
            'account_sync' => 'account_transactions.id',
        ];

        $query->orderBy($map[$sort] ?? 'reward_exchange_requests.id', $direction)
            ->orderBy('reward_exchange_requests.id', 'desc');
    }

    private function decorateRow($row)
    {
        $row->student_name = trim(($row->last_name ?? '') . ' ' . ($row->first_name ?? '')) ?: '-';
        $row->school_name = $row->school_name ?: '-';
        $row->student_code = $row->student_code ?: '-';
        $row->reward_category_name = $row->reward_category_name ?: '未分類';
        $row->reward_item_name = $row->reward_item_name ?: '-';
        $row->request_points = (int) ($row->request_points ?? 0);
        $row->cost_price = (int) ($row->cost_price ?? 0);
        $row->status_label = $this->statusOptions()[$row->status] ?? ($row->status_name ?: $row->status);
        $row->status_class = $this->statusClass($row->status);
        $row->requested_at = $this->formatDate($row->requested_at);
        $row->approved_at = $this->formatDate($row->approved_at ?? null);
        $row->delivered_at = $this->formatDate($row->delivered_at ?? null);
        $row->rejected_at = $this->formatDate($row->rejected_at ?? null);
        $row->created_at_display = $this->formatDateTime($row->created_at ?? null);
        $row->updated_at_display = $this->formatDateTime($row->updated_at ?? null);
        $row->account_created_at_display = $this->formatDateTime($row->account_created_at ?? null);
        $row->account_updated_at_display = $this->formatDateTime($row->account_updated_at ?? null);
        $row->account_source_table = self::ACCOUNT_SOURCE_TABLE;
        $row->account_source_id = $row->id;
        $row->account_sync_label = $row->account_transaction_id ? '反映済み' : '未反映';
        $row->account_sync_class = $row->account_transaction_id ? 'status-completed' : 'status-pending';
        $row->note = $row->note ?: '-';

        return $row;
    }

    private function buildSummary($rows): array
    {
        $now = now();
        $thisMonth = $now->format('Y-m');
        $lastMonth = $now->copy()->subMonth()->format('Y-m');

        $delivered = $rows->where('status', 'delivered');
        $thisMonthDelivered = $delivered->filter(fn ($row) => $row->delivered_at && Carbon::parse($row->delivered_at)->format('Y-m') === $thisMonth);
        $lastMonthDelivered = $delivered->filter(fn ($row) => $row->delivered_at && Carbon::parse($row->delivered_at)->format('Y-m') === $lastMonth);

        return [
            'total_count' => $rows->count(),
            'delivered_count' => $delivered->count(),
            'total_cost' => $delivered->sum('cost_price'),
            'total_points' => $rows->sum('request_points'),
            'not_synced_count' => $delivered->whereNull('account_transaction_id')->count(),
            'this_month_amount' => $thisMonthDelivered->sum('cost_price'),
            'last_month_amount' => $lastMonthDelivered->sum('cost_price'),
            'this_month_count' => $thisMonthDelivered->count(),
            'last_month_count' => $lastMonthDelivered->count(),
            'this_month_points' => $thisMonthDelivered->sum('request_points'),
            'last_month_points' => $lastMonthDelivered->sum('request_points'),
            'amount_yoy_rate' => $this->rate($thisMonthDelivered->sum('cost_price'), $lastMonthDelivered->sum('cost_price')),
            'count_yoy_rate' => $this->rate($thisMonthDelivered->count(), $lastMonthDelivered->count()),
        ];
    }

    private function buildCostAmountChartData($rows, string $period)
    {
        return $this->monthsForPeriod($period)
            ->map(function ($month) use ($rows) {
                $amount = $rows
                    ->where('status', 'delivered')
                    ->filter(fn ($row) => $row->delivered_at && Carbon::parse($row->delivered_at)->format('Y-m') === $month)
                    ->sum('cost_price');

                return ['month' => $month, 'amount' => $amount];
            })
            ->values();
    }

    private function buildCostCountChartData($rows, string $period)
    {
        return $this->monthsForPeriod($period)
            ->map(function ($month) use ($rows) {
                $items = $rows
                    ->where('status', 'delivered')
                    ->filter(fn ($row) => $row->delivered_at && Carbon::parse($row->delivered_at)->format('Y-m') === $month);

                return ['month' => $month, 'count' => $items->count()];
            })
            ->values();
    }

    private function buildCategoryChartData($rows)
    {
        return $rows
            ->where('status', 'delivered')
            ->groupBy('reward_category_name')
            ->map(fn ($items, $label) => ['label' => $label ?: '未分類', 'amount' => $items->sum('cost_price')])
            ->sortByDesc('amount')
            ->values();
    }

    private function syncAccountTransaction(int $exchangeRequestId): void
    {
        $category = AccountCategory::where('code', self::ACCOUNT_CATEGORY_CODE)->first();

        AccountTransaction::whereIn('source_table', [self::ACCOUNT_SOURCE_TABLE, self::LEGACY_ACCOUNT_SOURCE_TABLE])
            ->where('source_id', $exchangeRequestId)
            ->delete();

        $exchange = DB::table('reward_exchange_requests')
            ->join('students', 'reward_exchange_requests.student_id', '=', 'students.id')
            ->join('reward_items', 'reward_exchange_requests.reward_item_id', '=', 'reward_items.id')
            ->where('reward_exchange_requests.id', $exchangeRequestId)
            ->select([
                'reward_exchange_requests.id',
                'reward_exchange_requests.student_id',
                'reward_exchange_requests.status',
                'reward_exchange_requests.delivered_at',
                'students.school_id',
                'reward_items.name as reward_item_name',
                'reward_items.cost_price',
            ])
            ->first();

        if (! $category || ! $exchange || $exchange->status !== 'delivered') {
            return;
        }

        $date = $exchange->delivered_at ? Carbon::parse($exchange->delivered_at)->toDateString() : now()->toDateString();

        AccountTransaction::create([
            'scheduled_date' => $date,
            'transaction_date' => $date,
            'account_category_id' => $category->id,
            'payment_method_id' => null,
            'transaction_name' => 'ポイント商品費用：' . $exchange->reward_item_name,
            'amount' => (int) ($exchange->cost_price ?? 0),
            'before_discount_amount' => null,
            'discount_amount' => null,
            'discount_type_id' => null,
            'discount_note' => null,
            'status' => 'confirmed',
            'cancelled_reason' => null,
            'memo' => 'ポイント商品交換の受け渡し完了により自動計上',
            'school_id' => $exchange->school_id,
            'student_id' => $exchange->student_id,
            'source_table' => self::ACCOUNT_SOURCE_TABLE,
            'source_id' => $exchange->id,
            'related_transaction_id' => null,
            'created_by' => auth()->id() ?? 1,
            'updated_by' => auth()->id() ?? 1,
        ]);
    }

    private function insertPointTransaction(int $exchangeRequestId, int $studentId, $item, Carbon $occurredAt): void
    {
        DB::table('point_transactions')->insert([
            'student_id' => $studentId,
            'event_type' => 'reward_exchange',
            'event_type_name' => 'ポイント商品交換',
            'point_rule_code' => 'reward_exchange',
            'points' => -1 * (int) $item->required_points,
            'balance_after' => 0,
            'reason' => 'ポイント商品交換：' . $item->name,
            'related_id' => $exchangeRequestId,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statusOptions(): array
    {
        return [
            'requested' => '申請中',
            'approved' => '承認済',
            'delivered' => '受け渡し済み',
            'rejected' => '却下',
        ];
    }

    private function statusClass(?string $status): string
    {
        return match ($status) {
            'delivered' => 'status-completed',
            'approved' => 'status-paid',
            'rejected' => 'status-cancelled',
            default => 'status-pending',
        };
    }

    private function monthsForPeriod(string $period)
    {
        $months = match ($period) {
            'all' => 120,
            '5years' => 60,
            '3years' => 36,
            default => 12,
        };

        return collect(range($months - 1, 0))->map(fn ($i) => now()->copy()->subMonths($i)->format('Y-m'));
    }

    private function rate($current, $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return null;
        }

        return round(((float) $current - (float) $previous) / (float) $previous * 100, 1);
    }

    private function formatDate($value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    private function formatDateTime($value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y/m/d H:i') : null;
    }
}

