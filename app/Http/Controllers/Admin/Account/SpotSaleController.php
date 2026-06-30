<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\Discount;
use App\Models\PaymentMethod;
use App\Models\School;
use App\Models\SpotSale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SpotSaleController extends Controller
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
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $chartPeriod = $request->input('chart_period', '1year');
        if (! in_array($chartPeriod, ['all', '5years', '3years', '1year'], true)) {
            $chartPeriod = '1year';
        }

        return view('admin.account.spot-sales.index', [
            'sales' => $sales,
            'totalSalesCount' => $rows->count(),
            'summary' => $this->buildSummary($filteredRows),
            'spotSalesChartData' => $this->buildSpotSalesChartData($filteredRows, $chartPeriod),
            'spotCountChartData' => $this->buildSpotCountChartData($filteredRows, $chartPeriod),
            'chartPeriod' => $chartPeriod,
            'students' => DB::table('students')->select('id', 'student_code', 'last_name', 'first_name')->orderBy('id')->get(),
            'schools' => School::orderBy('name')->get(),
            'accountCategories' => AccountCategory::where('transaction_type', '収益')->where('name', 'スポット売上')->orderBy('sort_order')->get(),
            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),
            'discounts' => Discount::orderBy('sort_order')->get(),
            'spotCategories' => $rows->pluck('category')->filter()->unique()->sort()->values(),
            'filters' => $request->only([
                'scheduled_from', 'scheduled_to', 'transaction_from', 'transaction_to', 'school_id',
                'category', 'payment_method_id', 'discount_type_id', 'payment_status', 'keyword',
            ]),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->sortRows($this->applyFilters($this->getBaseRows(), $request), 'id', 'desc');
        $filename = 'spot_sales_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ID', '教室', '生徒', '生徒コード', '売上分類', '取引名', '数量', '会計区分',
                '取引予定日', '入出金方法', '取引日', '割引種別', '割引前金額', '割引額',
                '取引額(税込)', '入金状態', '取引メモ', '割引メモ',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id, $row->school_name, $row->student_name, $row->student_code, $row->category,
                    $row->sale_title, $row->quantity, $row->account_category_name, $row->scheduled_date,
                    $row->payment_method_name, $row->transaction_date, $row->discount_type_name,
                    $row->before_discount_amount, $row->discount_amount, $row->amount,
                    $row->payment_status_label, $row->transaction_note, $row->discount_note,
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => ['required', 'exists:students,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'sale_title' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'scheduled_date' => ['required', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payment_status' => ['required', 'in:paid,unpaid,cancelled'],
            'before_discount_amount' => ['required', 'integer', 'min:0'],
            'discount_type_id' => ['nullable', 'exists:discounts,id'],
            'discount_amount' => ['required', 'integer', 'min:0'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'discount_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $student = DB::table('students')->where('id', $request->student_id)->first();
            $category = AccountCategory::where('name', 'スポット売上')->firstOrFail();

            $before = (int) $request->before_discount_amount;
            $discount = min((int) $request->discount_amount, $before);
            $total = max(0, $before - $discount);
            $paymentStatus = $request->payment_status;
            $transactionDate = $paymentStatus === 'paid' ? ($request->transaction_date ?: now()->toDateString()) : null;

            $spotSale = SpotSale::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'payment_method_id' => $request->payment_method_id,
                'sale_code' => $this->generateSaleCode(),
                'category' => $request->category ?: 'その他',
                'sale_title' => $request->sale_title,
                'quantity' => (int) $request->quantity,
                'sale_date' => $request->scheduled_date,
                'payment_due_date' => $request->scheduled_date,
                'paid_at' => $transactionDate,
                'before_discount_amount' => $before,
                'discount_amount' => $discount,
                'tax_amount' => 0,
                'total_amount' => $total,
                'payment_status' => $paymentStatus,
                'memo' => $request->memo,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]);

            AccountTransaction::create([
                'scheduled_date' => $request->scheduled_date,
                'transaction_date' => $transactionDate,
                'account_category_id' => $category->id,
                'payment_method_id' => $request->payment_method_id,
                'transaction_name' => $request->sale_title,
                'amount' => $total,
                'before_discount_amount' => $before,
                'discount_amount' => $discount,
                'discount_type_id' => $discount > 0 ? $request->discount_type_id : null,
                'discount_note' => $request->discount_note,
                'status' => $this->toTransactionStatus($paymentStatus),
                'memo' => $request->memo,
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'source_table' => 'spot_sales',
                'source_id' => $spotSale->id,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]);
        });

        return redirect()->route('admin.operations.classroom-accounting.spot-sales.index')->with('success', 'スポット売上を登録しました。');
    }

    public function update(Request $request, int $spotSaleId)
    {
        $validator = Validator::make($request->all(), [
            'category' => ['nullable', 'string', 'max:100'],
            'sale_title' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'scheduled_date' => ['required', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_status' => ['required', 'in:paid,unpaid,cancelled'],
            'before_discount_amount' => ['required', 'integer', 'min:0'],
            'discount_type_id' => ['nullable', 'exists:discounts,id'],
            'discount_amount' => ['required', 'integer', 'min:0'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'discount_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '入力内容を確認してください。', 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $spotSaleId) {
            $spotSale = SpotSale::findOrFail($spotSaleId);
            $before = (int) $request->before_discount_amount;
            $discount = min((int) $request->discount_amount, $before);
            $total = max(0, $before - $discount);
            $paymentStatus = $request->payment_status;
            $transactionDate = $paymentStatus === 'paid' ? ($request->transaction_date ?: now()->toDateString()) : null;

            $spotSale->update([
                'payment_method_id' => $request->payment_method_id,
                'category' => $request->category ?: 'その他',
                'sale_title' => $request->sale_title,
                'quantity' => (int) $request->quantity,
                'sale_date' => $request->scheduled_date,
                'payment_due_date' => $request->scheduled_date,
                'paid_at' => $transactionDate,
                'before_discount_amount' => $before,
                'discount_amount' => $discount,
                'tax_amount' => 0,
                'total_amount' => $total,
                'payment_status' => $paymentStatus,
                'memo' => $request->memo,
                'updated_by' => auth()->id() ?? 1,
            ]);

            AccountTransaction::where('source_table', 'spot_sales')->where('source_id', $spotSale->id)->update([
                'scheduled_date' => $request->scheduled_date,
                'transaction_date' => $transactionDate,
                'payment_method_id' => $request->payment_method_id,
                'transaction_name' => $request->sale_title,
                'amount' => $total,
                'before_discount_amount' => $before,
                'discount_amount' => $discount,
                'discount_type_id' => $discount > 0 ? $request->discount_type_id : null,
                'discount_note' => $request->discount_note,
                'status' => $this->toTransactionStatus($paymentStatus),
                'memo' => $request->memo,
                'updated_by' => auth()->id() ?? 1,
                'updated_at' => now(),
            ]);
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
            ->select('students.id', 'students.student_code', 'students.last_name', 'students.first_name', 'schools.name as school_name')
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

    private function getBaseRows(): Collection
    {
        return DB::table('spot_sales')
            ->join('schools', 'spot_sales.school_id', '=', 'schools.id')
            ->leftJoin('students', 'spot_sales.student_id', '=', 'students.id')
            ->leftJoin('payment_methods', 'spot_sales.payment_method_id', '=', 'payment_methods.id')
            ->leftJoin('account_transactions', function ($join) {
                $join->on('account_transactions.source_id', '=', 'spot_sales.id')->where('account_transactions.source_table', 'spot_sales');
            })
            ->leftJoin('account_categories', 'account_transactions.account_category_id', '=', 'account_categories.id')
            ->leftJoin('discounts', 'account_transactions.discount_type_id', '=', 'discounts.id')
            ->leftJoin('users as created_users', 'spot_sales.created_by', '=', 'created_users.id')
            ->leftJoin('users as updated_users', 'spot_sales.updated_by', '=', 'updated_users.id')
            ->select([
                'spot_sales.id', 'spot_sales.sale_code', 'spot_sales.category', 'spot_sales.sale_title', 'spot_sales.quantity',
                'spot_sales.school_id', 'schools.name as school_name', 'spot_sales.student_id', 'students.student_code',
                DB::raw("CONCAT(COALESCE(students.last_name, ''), ' ', COALESCE(students.first_name, '')) as student_name"),
                'spot_sales.payment_method_id', 'payment_methods.name as payment_method_name',
                'spot_sales.payment_due_date as scheduled_date', 'spot_sales.paid_at as transaction_date',
                'spot_sales.before_discount_amount', 'spot_sales.discount_amount', 'spot_sales.total_amount as amount',
                'spot_sales.payment_status', 'spot_sales.memo as transaction_note',
                'account_transactions.id as account_transaction_id', 'account_transactions.discount_type_id',
                'account_transactions.discount_note', 'account_transactions.status as account_status',
                'account_categories.name as account_category_name', 'discounts.name as discount_type_name',
                'created_users.name as created_by_name', 'updated_users.name as updated_by_name',
                'spot_sales.created_at', 'spot_sales.updated_at',
            ])
            ->orderByDesc('spot_sales.id')
            ->get()
            ->map(function ($row) {
                $row->student_name = trim((string) $row->student_name) ?: '-';
                $row->student_code = $row->student_code ?: '-';
                $row->payment_method_name = $row->payment_method_name ?: '未設定';
                $row->discount_type_name = $row->discount_type_name ?: '割引なし';
                $row->discount_note = $row->discount_note ?: '-';
                $row->transaction_note = $row->transaction_note ?: '-';
                $row->account_category_name = $row->account_category_name ?: 'スポット売上';
                $row->payment_status_label = $this->paymentStatusLabel($row->payment_status);
                $row->payment_status_class = $this->paymentStatusClass($row->payment_status);
                $row->payment_status_form_value = $this->paymentStatusFormValue($row->payment_status);
                $row->scheduled_date = $row->scheduled_date ? Carbon::parse($row->scheduled_date)->toDateString() : null;
                $row->transaction_date = $row->transaction_date ? Carbon::parse($row->transaction_date)->toDateString() : null;
                return $row;
            });
    }

    private function applyFilters(Collection $rows, Request $request): Collection
    {
        return $rows
            ->when($request->filled('scheduled_from'), fn ($collection) => $collection->where('scheduled_date', '>=', $request->scheduled_from))
            ->when($request->filled('scheduled_to'), fn ($collection) => $collection->where('scheduled_date', '<=', $request->scheduled_to))
            ->when($request->filled('transaction_from'), fn ($collection) => $collection->where('transaction_date', '>=', $request->transaction_from))
            ->when($request->filled('transaction_to'), fn ($collection) => $collection->where('transaction_date', '<=', $request->transaction_to))
            ->when($request->filled('school_id'), fn ($collection) => $collection->where('school_id', (int) $request->school_id))
            ->when($request->filled('category'), fn ($collection) => $collection->where('category', $request->category))
            ->when($request->filled('payment_method_id'), fn ($collection) => $collection->where('payment_method_id', (int) $request->payment_method_id))
            ->when($request->filled('discount_type_id'), fn ($collection) => $collection->where('discount_type_id', (int) $request->discount_type_id))
            ->when($request->filled('payment_status'), fn ($collection) => $collection->where('payment_status', $request->payment_status))
            ->when($request->filled('keyword'), function ($collection) use ($request) {
                $keyword = mb_strtolower((string) $request->keyword);
                return $collection->filter(function ($row) use ($keyword) {
                    return str_contains(mb_strtolower(implode(' ', [
                        $row->sale_code, $row->sale_title, $row->category, $row->student_name, $row->student_code,
                        $row->school_name, $row->transaction_note, $row->discount_note,
                    ])), $keyword);
                });
            })
            ->values();
    }

    private function sortRows(Collection $rows, string $sort, string $direction): Collection
    {
        $map = [
            'id' => 'id', 'school' => 'school_name', 'student' => 'student_name', 'student_code' => 'student_code',
            'category' => 'category', 'sale_title' => 'sale_title', 'quantity' => 'quantity', 'account_category' => 'account_category_name',
            'scheduled_date' => 'scheduled_date', 'payment_method' => 'payment_method_name', 'transaction_date' => 'transaction_date',
            'before_discount_amount' => 'before_discount_amount', 'discount_amount' => 'discount_amount', 'amount' => 'amount',
            'payment_status' => 'payment_status_label',
        ];
        $key = $map[$sort] ?? 'id';
        return ($direction === 'asc' ? $rows->sortBy($key) : $rows->sortByDesc($key))->values();
    }

    private function buildSummary(Collection $rows): array
    {
        $total = (int) $rows->sum('amount');
        $paid = (int) $rows->where('payment_status', 'paid')->sum('amount');
        $unpaid = (int) $rows->where('payment_status', 'unpaid')->sum('amount');
        $cancelled = (int) $rows->where('payment_status', 'cancelled')->sum('amount');

        return [
            'count' => $rows->count(),
            'total_amount' => $total,
            'paid_amount' => $paid,
            'unpaid_amount' => $unpaid,
            'cancelled_amount' => $cancelled,
            'collection_rate' => $total > 0 ? round($paid / $total * 100, 1) : 0,
        ];
    }

    private function buildSpotSalesChartData(Collection $rows, string $period): array
    {
        $labels = $this->buildSpotMonthLabels($rows, $period);

        return $labels->map(function ($label) use ($rows) {
            $amount = $rows->filter(function ($row) use ($label) {
                $date = $row->transaction_date ?: $row->scheduled_date;
                return $date && Carbon::parse($date)->format('Y/m') === $label;
            })->sum('amount');

            return [
                'month' => $label,
                'amount' => (int) $amount,
            ];
        })->values()->all();
    }

    private function buildSpotCountChartData(Collection $rows, string $period): array
    {
        $labels = $this->buildSpotMonthLabels($rows, $period);

        return $labels->map(function ($label) use ($rows) {
            $count = $rows->filter(function ($row) use ($label) {
                $date = $row->transaction_date ?: $row->scheduled_date;
                return $date && Carbon::parse($date)->format('Y/m') === $label;
            })->count();

            return [
                'month' => $label,
                'count' => $count,
            ];
        })->values()->all();
    }

    private function buildSpotMonthLabels(Collection $rows, string $period): Collection
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

    private function generateSaleCode(): string
    {
        $prefix = 'SPOT-' . now()->format('Ymd') . '-';
        $next = SpotSale::where('sale_code', 'like', $prefix . '%')->count() + 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function toTransactionStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {'paid' => 'confirmed', 'cancelled' => 'cancelled', default => 'planned'};
    }

    private function paymentStatusLabel(?string $status): string
    {
        return match ($status) {'paid' => '入金済', 'cancelled' => '取消', default => '未入金'};
    }

    private function paymentStatusClass(?string $status): string
    {
        return match ($status) {'paid' => 'paid', 'cancelled' => 'cancelled', default => 'unpaid'};
    }

    private function paymentStatusFormValue(?string $status): string
    {
        return match ($status) {'paid' => 'paid', 'cancelled' => 'cancelled', default => 'unpaid'};
    }
}
