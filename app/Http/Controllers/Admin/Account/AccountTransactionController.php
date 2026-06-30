<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountTransaction;
use App\Models\AccountCategory;
use App\Models\PaymentMethod;
use App\Models\Discount;
use App\Models\School;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountTransactionController extends Controller
{
    public function index(Request $request)
    {
        $this->syncLinkedTransactionDates();

        $query = AccountTransaction::query()
            ->with([
                'accountCategory',
                'paymentMethod',
                'student',
                'school',
                'discountType',
            ]);

        $this->applyFilters($query, $request);

        $chartBaseQuery = clone $query;

        $sort = $request->input('sort', 'transaction_date');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'transaction_type',
            'account_category',
            'school',
            'student',
            'scheduled_date',
            'payment_method',
            'transaction_date',
            'amount',
            'status',
        ];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'transaction_date';
        }

        match ($sort) {
            'transaction_type' => $query
                ->leftJoin('account_categories as sort_account_categories', 'account_transactions.account_category_id', '=', 'sort_account_categories.id')
                ->orderBy('sort_account_categories.transaction_type', $direction)
                ->select('account_transactions.*'),

            'account_category' => $query
                ->leftJoin('account_categories as sort_account_categories', 'account_transactions.account_category_id', '=', 'sort_account_categories.id')
                ->orderBy('sort_account_categories.name', $direction)
                ->select('account_transactions.*'),

            'school' => $query
                ->leftJoin('schools as sort_schools', 'account_transactions.school_id', '=', 'sort_schools.id')
                ->orderBy('sort_schools.name', $direction)
                ->select('account_transactions.*'),

            'student' => $query
                ->leftJoin('students as sort_students', 'account_transactions.student_id', '=', 'sort_students.id')
                ->orderByRaw("CONCAT(COALESCE(sort_students.last_name, ''), ' ', COALESCE(sort_students.first_name, '')) {$direction}")
                ->select('account_transactions.*'),

            'payment_method' => $query
                ->leftJoin('payment_methods as sort_payment_methods', 'account_transactions.payment_method_id', '=', 'sort_payment_methods.id')
                ->orderBy('sort_payment_methods.name', $direction)
                ->select('account_transactions.*'),

            'scheduled_date' => $query->orderBy('scheduled_date', $direction),
            'transaction_date' => $query->orderBy('transaction_date', $direction),
            'amount' => $query->orderBy('amount', $direction),
            'status' => $query->orderBy('status', $direction),

            default => $query->orderBy('transaction_date', 'desc'),
        };

        $transactions = $query
            ->orderBy('account_transactions.id', 'desc')
            ->paginate(50)
            ->withQueryString();

        $chartPeriod = $request->input('chart_period', '1year');

        if (!in_array($chartPeriod, ['all', '5years', '3years', '1year'], true)) {
            $chartPeriod = '1year';
        }

        return view(
            'admin.account.transactions.index',
            [
                'transactions' => $transactions,
                'monthlyChartData' => $this->buildMonthlyChartData($chartPeriod, $chartBaseQuery),
                'chartPeriod' => $chartPeriod,

                'schools' => School::orderBy('name')->get(),
                'accountCategories' => AccountCategory::orderBy('sort_order')->get(),
                'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),
                'discounts' => Discount::orderBy('id')->get(),

                'filters' => $request->only([
                    'scheduled_from',
                    'scheduled_to',
                    'transaction_from',
                    'transaction_to',
                    'school_id',
                    'transaction_type',
                    'account_category_id',
                    'payment_method_id',
                    'discount_type_id',
                    'status',
                    'keyword',
                ]),

                'sort' => $sort,
                'direction' => $direction,
            ]
        );
    }

    public function export(Request $request)
    {
        $this->syncLinkedTransactionDates();

        $query = AccountTransaction::query()
            ->with([
                'accountCategory',
                'paymentMethod',
                'student',
                'school',
                'discountType',
            ]);

        $this->applyFilters($query, $request);

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $statusLabels = [
            'planned' => '取引中',
            'confirmed' => '取引完了',
            'cancelled' => '取消',
        ];

        $escapeCsv = function ($value): string {
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $value = $value->name;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y/m/d H:i:s');
            } elseif (is_object($value)) {
                $value = method_exists($value, '__toString') ? (string) $value : '';
            }

            $value = (string) ($value ?? '');
            $value = str_replace('"', '""', $value);

            return '"' . $value . '"';
        };

        $makeCsvLine = function (array $values) use ($escapeCsv): string {
            return implode(',', array_map($escapeCsv, $values)) . "\r\n";
        };

        $csv = "\xEF\xBB\xBF";

        $csv .= $makeCsvLine([
            'ID',
            '収支区分',
            '会計カテゴリ',
            '取引名',
            '教室',
            '生徒',
            '取引予定日',
            '入出金方法',
            '取引日',
            '割引前金額',
            '割引額',
            '取引額',
            '状態',
            '割引種別',
            '割引メモ',
            '元データテーブル',
            '元データID',
        ]);

        foreach ($transactions as $row) {
            $csv .= $makeCsvLine([
                $row->id,
                $row->accountCategory?->transaction_type ?? '',
                $row->accountCategory?->name ?? '',
                $row->transaction_name ?? '',
                $row->school?->name ?? '',
                trim(($row->student?->last_name ?? '') . ' ' . ($row->student?->first_name ?? '')) ?: '',
                $row->scheduled_date ? Carbon::parse($row->scheduled_date)->format('Y/m/d') : '',
                $row->paymentMethod?->name ?? '',
                $row->transaction_date ? Carbon::parse($row->transaction_date)->format('Y/m/d') : '',
                $row->before_discount_amount ?? $row->amount ?? 0,
                $row->discount_amount ?? 0,
                $row->amount ?? 0,
                $statusLabels[$row->status] ?? $row->status ?? '',
                $row->discountType?->name ?? '',
                $row->discount_note ?? '',
                $row->source_table ?? '',
                $row->source_id ?? '',
            ]);
        }

        $filename = 'account_transactions_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function syncLinkedTransactionDates(): void
    {
        if (! Schema::hasTable('account_transactions')) {
            return;
        }

        if (Schema::hasTable('spot_sales') && Schema::hasColumn('spot_sales', 'paid_at')) {
            DB::statement(<<<'SQL'
                UPDATE account_transactions AS account_transactions_sync
                SET transaction_date = DATE(spot_sales.paid_at)
                FROM spot_sales
                WHERE account_transactions_sync.source_table = 'spot_sales'
                  AND account_transactions_sync.source_id = spot_sales.id
                  AND account_transactions_sync.transaction_date IS DISTINCT FROM DATE(spot_sales.paid_at)
            SQL);
        }

        if (Schema::hasTable('shop_orders') && Schema::hasColumn('shop_orders', 'transaction_date')) {
            DB::statement(<<<'SQL'
                UPDATE account_transactions AS account_transactions_sync
                SET transaction_date = shop_orders.transaction_date
                FROM shop_orders
                WHERE account_transactions_sync.source_table = 'shop_order'
                  AND account_transactions_sync.source_id = shop_orders.id
                  AND account_transactions_sync.transaction_date IS DISTINCT FROM shop_orders.transaction_date
            SQL);
        } elseif (Schema::hasTable('shop_orders') && Schema::hasColumn('shop_orders', 'paid_at')) {
            DB::statement(<<<'SQL'
                UPDATE account_transactions AS account_transactions_sync
                SET transaction_date = DATE(shop_orders.paid_at)
                FROM shop_orders
                WHERE account_transactions_sync.source_table = 'shop_order'
                  AND account_transactions_sync.source_id = shop_orders.id
                  AND account_transactions_sync.transaction_date IS DISTINCT FROM DATE(shop_orders.paid_at)
            SQL);
        }

        if (Schema::hasTable('event_payments') && Schema::hasColumn('event_payments', 'paid_at')) {
            DB::statement(<<<'SQL'
                UPDATE account_transactions AS account_transactions_sync
                SET transaction_date = linked_event_payments.paid_at_date
                FROM (
                    SELECT event_application_id, MAX(DATE(paid_at)) AS paid_at_date
                    FROM event_payments
                    GROUP BY event_application_id
                ) AS linked_event_payments
                WHERE account_transactions_sync.source_table = 'event_application'
                  AND account_transactions_sync.source_id = linked_event_payments.event_application_id
                  AND account_transactions_sync.transaction_date IS DISTINCT FROM linked_event_payments.paid_at_date
            SQL);
        }

        if (
            Schema::hasTable('invoice_items')
            && Schema::hasTable('payments')
            && Schema::hasColumn('invoice_items', 'invoice_id')
            && Schema::hasColumn('payments', 'invoice_id')
            && Schema::hasColumn('payments', 'payment_date')
        ) {
            DB::statement(<<<'SQL'
                UPDATE account_transactions AS account_transactions_sync
                SET transaction_date = linked_invoice_payments.payment_date
                FROM (
                    SELECT invoice_items.id AS invoice_item_id, MAX(payments.payment_date) AS payment_date
                    FROM invoice_items
                    INNER JOIN payments ON payments.invoice_id = invoice_items.invoice_id
                    GROUP BY invoice_items.id
                ) AS linked_invoice_payments
                WHERE account_transactions_sync.source_table = 'invoice_item'
                  AND account_transactions_sync.source_id = linked_invoice_payments.invoice_item_id
                  AND account_transactions_sync.transaction_date IS DISTINCT FROM linked_invoice_payments.payment_date
            SQL);
        }
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('scheduled_from')) {
            $query->whereDate('scheduled_date', '>=', $request->scheduled_from);
        }

        if ($request->filled('scheduled_to')) {
            $query->whereDate('scheduled_date', '<=', $request->scheduled_to);
        }

        if ($request->filled('transaction_from')) {
            $query->whereDate('transaction_date', '>=', $request->transaction_from);
        }

        if ($request->filled('transaction_to')) {
            $query->whereDate('transaction_date', '<=', $request->transaction_to);
        }

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->filled('account_category_id')) {
            $query->where('account_category_id', $request->account_category_id);
        }

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        if ($request->filled('discount_type_id')) {
            $query->where('discount_type_id', $request->discount_type_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transaction_type')) {
            $query->whereHas('accountCategory', function ($q) use ($request) {
                $q->where('transaction_type', $request->transaction_type);
            });
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('transaction_name', 'like', "%{$keyword}%")
                    ->orWhere('source_table', 'like', "%{$keyword}%")
                    ->orWhere('discount_note', 'like', "%{$keyword}%")
                    ->orWhereHas('student', function ($studentQuery) use ($keyword) {
                        $studentQuery->where('student_code', 'like', "%{$keyword}%")
                            ->orWhere('last_name', 'like', "%{$keyword}%")
                            ->orWhere('first_name', 'like', "%{$keyword}%")
                            ->orWhereRaw("CONCAT(last_name, first_name) LIKE ?", ["%{$keyword}%"])
                            ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$keyword}%"]);
                    });
            });
        }
    }

    private function buildMonthlyChartData(string $period, $baseQuery)
    {
        $now = Carbon::now()->startOfMonth();

        if ($period === '1year') {
            return $this->buildMonthChart($now, 12, $baseQuery);
        }

        if ($period === '3years') {
            return $this->buildQuarterChart($now, 12, $baseQuery);
        }

        if ($period === '5years') {
            return $this->buildYearChart(
                $now->copy()->subYears(4)->startOfYear(),
                $now->copy()->endOfYear(),
                $baseQuery
            );
        }

        $firstDate = (clone $baseQuery)
            ->whereNotNull('transaction_date')
            ->min('transaction_date');

        if (!$firstDate) {
            return collect();
        }

        return $this->buildYearChart(
            Carbon::parse($firstDate)->startOfYear(),
            $now->copy()->endOfYear(),
            $baseQuery
        );
    }

    private function buildMonthChart(Carbon $baseMonth, int $months, $baseQuery)
    {
        $runningProfit = 0;

        return collect(range($months - 1, 0))
            ->map(function ($monthsAgo) use ($baseMonth, &$runningProfit, $baseQuery) {
                $month = $baseMonth->copy()->subMonths($monthsAgo);

                $totals = $this->getTotals(
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                    $baseQuery
                );

                $runningProfit += $totals['profit'];

                return [
                    'label' => $month->format('Y/m'),
                    'income' => $totals['income'],
                    'expense' => $totals['expense'],
                    'profit' => $totals['profit'],
                    'cumulative_profit' => $runningProfit,
                ];
            })
            ->values();
    }

    private function buildQuarterChart(Carbon $baseMonth, int $quarters, $baseQuery)
    {
        $runningProfit = 0;

        return collect(range($quarters - 1, 0))
            ->map(function ($quartersAgo) use ($baseMonth, &$runningProfit, $baseQuery) {
                $quarterStart = $baseMonth->copy()
                    ->subQuarters($quartersAgo)
                    ->startOfQuarter();

                $quarterEnd = $quarterStart->copy()->endOfQuarter();

                $totals = $this->getTotals($quarterStart, $quarterEnd, $baseQuery);

                $runningProfit += $totals['profit'];

                return [
                    'label' => $quarterStart->format('Y') . ' Q' . $quarterStart->quarter,
                    'income' => $totals['income'],
                    'expense' => $totals['expense'],
                    'profit' => $totals['profit'],
                    'cumulative_profit' => $runningProfit,
                ];
            })
            ->values();
    }

    private function buildYearChart(Carbon $start, Carbon $end, $baseQuery)
    {
        $runningProfit = 0;
        $years = range((int) $start->format('Y'), (int) $end->format('Y'));

        return collect($years)
            ->map(function ($year) use (&$runningProfit, $baseQuery) {
                $start = Carbon::create($year, 1, 1)->startOfDay();
                $end = Carbon::create($year, 12, 31)->endOfDay();

                $totals = $this->getTotals($start, $end, $baseQuery);

                $runningProfit += $totals['profit'];

                return [
                    'label' => (string) $year,
                    'income' => $totals['income'],
                    'expense' => $totals['expense'],
                    'profit' => $totals['profit'],
                    'cumulative_profit' => $runningProfit,
                ];
            })
            ->values();
    }

    private function getTotals(Carbon $start, Carbon $end, $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->with('accountCategory')
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $income = $rows
            ->filter(fn ($row) => $row->accountCategory?->transaction_type === '収益')
            ->sum('amount');

        $expense = $rows
            ->filter(fn ($row) => $row->accountCategory?->transaction_type === '費用')
            ->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'profit' => $income - $expense,
        ];
    }
}