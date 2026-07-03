<?php

namespace App\Http\Controllers\Admin\Account;

use App\Enums\AccountTransactionSourceType;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        $summaryRows = (clone $query)->get();

        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $this->applySort($query, $sort, $direction);

        $expenses = $query->paginate(50)->withQueryString();
        $expenses->getCollection()->transform(fn (Expense $expense) => $this->decorateExpense($expense));

        $chartPeriod = $request->input('chart_period', '1year');
        if (! in_array($chartPeriod, ['all', '5years', '3years', '1year'], true)) {
            $chartPeriod = '1year';
        }

        return view('admin.account.expenses.index', [
            'expenses' => $expenses,
            'totalExpenseCount' => Expense::count(),
            'summary' => $this->buildSummary($summaryRows),
            'expenseAmountChartData' => $this->buildExpenseAmountChartData($summaryRows, $chartPeriod),
            'expenseCountChartData' => $this->buildExpenseCountChartData($summaryRows, $chartPeriod),
            'chartPeriod' => $chartPeriod,
            'schools' => School::orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),
            'expenseCategories' => ExpenseCategory::options(),
            'expenseStatuses' => ExpenseStatus::options(),
            'filters' => $request->only([
                'scheduled_from', 'scheduled_to', 'paid_from', 'paid_to', 'school_id',
                'expense_category', 'payment_method_id', 'payment_status', 'keyword',
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
        $rows = $query->get()->map(fn (Expense $expense) => $this->decorateExpense($expense));
        $filename = 'expenses_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['ID', '経費番号', '教室', '経費分類', '経費名', '支払先', '支払予定日', '支払日', '支払方法', '税抜金額', '消費税', '税込金額', '支払状況', 'メモ']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->expense_code,
                    $row->school_name,
                    $row->expense_category_label,
                    $row->expense_title,
                    $row->vendor_name,
                    $row->scheduled_date_text,
                    $row->paid_at_text,
                    $row->payment_method_name,
                    $row->amount,
                    $row->tax_amount,
                    $row->total_amount,
                    $row->payment_status_label,
                    $row->memo,
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => ['required', 'exists:schools,id'],
            'expense_category' => ['required', 'in:' . implode(',', array_column(ExpenseCategory::options(), 'value'))],
            'expense_title' => ['required', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'scheduled_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'amount' => ['required', 'integer', 'min:0'],
            'tax_amount' => ['required', 'integer', 'min:0'],
            'payment_status' => ['required', 'in:unpaid,paid,cancelled'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $amount = (int) $request->amount;
            $taxAmount = (int) $request->tax_amount;
            $status = ExpenseStatus::from((string) $request->payment_status);

            $expense = Expense::create([
                'school_id' => (int) $request->school_id,
                'expense_code' => $this->generateExpenseCode(),
                'expense_category' => $request->expense_category,
                'expense_title' => $request->expense_title,
                'vendor_name' => $request->vendor_name,
                'scheduled_date' => $request->scheduled_date,
                'paid_at' => $status === ExpenseStatus::Paid ? ($request->paid_at ?: now()->toDateString()) : null,
                'payment_method_id' => $request->payment_method_id,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $amount + $taxAmount,
                'payment_status' => $status->value,
                'memo' => $request->memo,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]);

            $this->upsertAccountTransaction($expense);
        });

        return redirect()->route('admin.operations.classroom-accounting.expenses.index')->with('success', '経費を登録しました。');
    }

    public function update(Request $request, int $expenseId)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => ['required', 'exists:schools,id'],
            'expense_category' => ['required', 'in:' . implode(',', array_column(ExpenseCategory::options(), 'value'))],
            'expense_title' => ['required', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'scheduled_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'integer', 'min:0'],
            'tax_amount' => ['required', 'integer', 'min:0'],
            'payment_status' => ['required', 'in:unpaid,paid,cancelled'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '入力内容を確認してください。', 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $expenseId) {
            $expense = Expense::findOrFail($expenseId);
            $amount = (int) $request->amount;
            $taxAmount = (int) $request->tax_amount;
            $status = ExpenseStatus::from((string) $request->payment_status);

            $expense->update([
                'school_id' => (int) $request->school_id,
                'expense_category' => $request->expense_category,
                'expense_title' => $request->expense_title,
                'vendor_name' => $request->vendor_name,
                'scheduled_date' => $request->scheduled_date,
                'paid_at' => $status === ExpenseStatus::Paid ? ($request->paid_at ?: now()->toDateString()) : null,
                'payment_method_id' => $request->payment_method_id,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $amount + $taxAmount,
                'payment_status' => $status->value,
                'memo' => $request->memo,
                'updated_by' => auth()->id() ?? 1,
            ]);

            $this->upsertAccountTransaction($expense->fresh());
        });

        return response()->json(['message' => '更新しました。']);
    }

    private function baseQuery()
    {
        return Expense::query()
            ->with(['school', 'paymentMethod', 'creator', 'updater']);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('scheduled_from')) {
            $query->whereDate('scheduled_date', '>=', $request->scheduled_from);
        }
        if ($request->filled('scheduled_to')) {
            $query->whereDate('scheduled_date', '<=', $request->scheduled_to);
        }
        if ($request->filled('paid_from')) {
            $query->whereDate('paid_at', '>=', $request->paid_from);
        }
        if ($request->filled('paid_to')) {
            $query->whereDate('paid_at', '<=', $request->paid_to);
        }
        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }
        if ($request->filled('expense_category')) {
            $query->where('expense_category', $request->expense_category);
        }
        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->payment_method_id);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->keyword);
            $query->where(function ($q) use ($keyword) {
                $q->where('expense_code', 'like', "%{$keyword}%")
                    ->orWhere('expense_title', 'like', "%{$keyword}%")
                    ->orWhere('vendor_name', 'like', "%{$keyword}%")
                    ->orWhere('memo', 'like', "%{$keyword}%");
            });
        }
    }

    private function applySort($query, string $sort, string $direction): void
    {
        $sortable = [
            'id' => 'id',
            'expense_code' => 'expense_code',
            'scheduled_date' => 'scheduled_date',
            'paid_at' => 'paid_at',
            'expense_category' => 'expense_category',
            'expense_title' => 'expense_title',
            'vendor_name' => 'vendor_name',
            'amount' => 'amount',
            'tax_amount' => 'tax_amount',
            'total_amount' => 'total_amount',
            'school' => 'school_id',
            'payment_status' => 'payment_status',
        ];

        $query->orderBy($sortable[$sort] ?? 'id', $direction)->orderBy('id', 'desc');
    }

    private function decorateExpense(Expense $expense): Expense
    {
        $category = $expense->expense_category instanceof ExpenseCategory
            ? $expense->expense_category
            : ExpenseCategory::tryFrom((string) $expense->expense_category);
        $status = $expense->payment_status instanceof ExpenseStatus
            ? $expense->payment_status
            : ExpenseStatus::tryFrom((string) $expense->payment_status);

        $expense->school_name = $expense->school?->name ?? '-';
        $expense->payment_method_name = $expense->paymentMethod?->name ?? '-';
        $expense->expense_category_value = $category?->value ?? (string) $expense->expense_category;
        $expense->expense_category_label = $category?->label() ?? (string) $expense->expense_category;
        $expense->payment_status_value = $status?->value ?? (string) $expense->payment_status;
        $expense->payment_status_label = $status?->label() ?? (string) $expense->payment_status;
        $expense->scheduled_date_text = $expense->scheduled_date ? $expense->scheduled_date->format('Y/m/d') : '-';
        $expense->paid_at_text = $expense->paid_at ? $expense->paid_at->format('Y/m/d') : '-';
        $expense->scheduled_date_input = $expense->scheduled_date ? $expense->scheduled_date->format('Y-m-d') : '';
        $expense->paid_at_input = $expense->paid_at ? $expense->paid_at->format('Y-m-d') : '';
        $expense->creator_name = $expense->creator?->name ?? '-';
        $expense->updater_name = $expense->updater?->name ?? '-';
        $expense->created_at_text = $expense->created_at ? $expense->created_at->format('Y/m/d H:i') : '-';
        $expense->updated_at_text = $expense->updated_at ? $expense->updated_at->format('Y/m/d H:i') : '-';

        return $expense;
    }

    private function buildSummary($rows): array
    {
        $now = Carbon::now();
        $currentMonthRows = $rows->filter(fn ($row) => $row->scheduled_date && $row->scheduled_date->isSameMonth($now));
        $paidRows = $currentMonthRows->filter(fn ($row) => $this->statusValue($row) === ExpenseStatus::Paid->value);
        $unpaidRows = $rows->filter(fn ($row) => $this->statusValue($row) === ExpenseStatus::Unpaid->value);
        $activeRows = $rows->filter(fn ($row) => $this->statusValue($row) !== ExpenseStatus::Cancelled->value);

        return [
            'current_month_total' => (int) $currentMonthRows->filter(fn ($row) => $this->statusValue($row) !== ExpenseStatus::Cancelled->value)->sum('total_amount'),
            'current_month_paid' => (int) $paidRows->sum('total_amount'),
            'unpaid_total' => (int) $unpaidRows->sum('total_amount'),
            'average_amount' => $activeRows->count() > 0 ? (int) round($activeRows->avg('total_amount')) : 0,
            'total_count' => $rows->count(),
        ];
    }

    private function buildExpenseAmountChartData($rows, string $period): array
    {
        $filtered = $this->filterRowsByChartPeriod($rows, $period);
        $monthly = $filtered
            ->filter(fn ($row) => $row->scheduled_date && $this->statusValue($row) !== ExpenseStatus::Cancelled->value)
            ->groupBy(fn ($row) => $row->scheduled_date->format('Y-m'))
            ->map(fn ($items) => (int) $items->sum('total_amount'));

        $labels = $this->chartMonthLabels($rows, $period);

        return [
            'labels' => $labels,
            'values' => collect($labels)->map(fn ($month) => (int) ($monthly[$month] ?? 0))->values(),
        ];
    }

    private function buildExpenseCountChartData($rows, string $period): array
    {
        $filtered = $this->filterRowsByChartPeriod($rows, $period);
        $monthly = $filtered
            ->filter(fn ($row) => $row->scheduled_date && $this->statusValue($row) !== ExpenseStatus::Cancelled->value)
            ->groupBy(fn ($row) => $row->scheduled_date->format('Y-m'))
            ->map(fn ($items) => (int) $items->count());

        $labels = $this->chartMonthLabels($rows, $period);

        return [
            'labels' => $labels,
            'values' => collect($labels)->map(fn ($month) => (int) ($monthly[$month] ?? 0))->values(),
        ];
    }

    private function filterRowsByChartPeriod($rows, string $period)
    {
        if ($period === 'all') {
            return $rows;
        }

        $years = match ($period) {
            '5years' => 5,
            '3years' => 3,
            default => 1,
        };

        $from = Carbon::now()->subYears($years)->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        return $rows->filter(fn ($row) => $row->scheduled_date
            && $row->scheduled_date->greaterThanOrEqualTo($from)
            && $row->scheduled_date->lessThanOrEqualTo($to));
    }

    private function chartMonthLabels($rows, string $period)
    {
        if ($period === 'all') {
            $dates = $rows->filter(fn ($row) => $row->scheduled_date)->pluck('scheduled_date');
            if ($dates->isEmpty()) {
                return collect();
            }
            $from = $dates->min()->copy()->startOfMonth();
            $to = $dates->max()->copy()->startOfMonth();
        } else {
            $years = match ($period) {
                '5years' => 5,
                '3years' => 3,
                default => 1,
            };
            $from = Carbon::now()->subYears($years)->startOfMonth();
            $to = Carbon::now()->startOfMonth();
        }

        $labels = collect();
        $cursor = $from->copy();
        while ($cursor->lessThanOrEqualTo($to)) {
            $labels->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        return $labels;
    }

    private function upsertAccountTransaction(Expense $expense): void
    {
        $accountCategory = AccountCategory::where('code', 'expense')->first();
        if (! $accountCategory) {
            return;
        }

        $status = $expense->payment_status instanceof ExpenseStatus
            ? $expense->payment_status
            : ExpenseStatus::from((string) $expense->payment_status);

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
                'cancelled_reason' => $status === ExpenseStatus::Cancelled ? '経費画面で取消' : null,
                'memo' => $expense->memo,
                'school_id' => $expense->school_id,
                'student_id' => null,
                'created_by' => $expense->created_by,
            ]
        );
    }

    private function generateExpenseCode(): string
    {
        $prefix = 'EXP-' . now()->format('Ym') . '-';
        $latest = Expense::where('expense_code', 'like', $prefix . '%')
            ->orderByDesc('expense_code')
            ->value('expense_code');

        $next = $latest ? ((int) substr($latest, -3)) + 1 : 1;
        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function statusValue(Expense $expense): string
    {
        return $expense->payment_status instanceof ExpenseStatus
            ? $expense->payment_status->value
            : (string) $expense->payment_status;
    }

    private function categoryValue(Expense $expense): string
    {
        return $expense->expense_category instanceof ExpenseCategory
            ? $expense->expense_category->value
            : (string) $expense->expense_category;
    }
}
