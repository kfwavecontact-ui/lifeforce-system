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

class TuitionEnrollmentSaleController extends Controller
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

        if (!in_array($chartPeriod, ['all', '5years', '3years', '1year'], true)) {
            $chartPeriod = '1year';
        }

        return view('admin.account.tuition-enrollment-sales.index', [
            'sales' => $sales,
            'summary' => $this->buildSummary($filteredRows),
            'tuitionSalesChartData' => $this->buildTuitionSalesChartData($filteredRows, $chartPeriod),
            'courseCompositionData' => $this->buildCourseCompositionData($filteredRows),
            'chartPeriod' => $chartPeriod,

            'students' => DB::table('students')
                ->select('id', 'student_code', 'last_name', 'first_name')
                ->orderBy('id')
                ->get(),

            'schools' => School::orderBy('name')->get(),

            'accountCategories' => AccountCategory::where('transaction_type', '収益')
                ->whereIn('name', ['授業料売上', '入会金売上'])
                ->orderBy('sort_order')
                ->get(),

            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),
            'discounts' => Discount::orderBy('sort_order')->get(),

            'courseNames' => $rows->pluck('course_name')->filter()->unique()->sort()->values(),
            'attendanceTypes' => $rows->pluck('attendance_type')->filter()->unique()->sort()->values(),

            'filters' => $request->only([
                'scheduled_from',
                'scheduled_to',
                'transaction_from',
                'transaction_to',
                'school_id',
                'account_category_id',
                'payment_method_id',
                'discount_type_id',
                'payment_status',
                'course_name',
                'attendance_type',
                'keyword',
            ]),

            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->applyFilters($this->getBaseRows(), $request);
        $rows = $this->sortRows($rows, 'id', 'desc');

        $filename = 'tuition_enrollment_sales_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ID',
                '教室',
                '生徒',
                '生徒コード',
                'コース名',
                '通塾種別',
                '会計区分',
                '取引予定日',
                '入出金方法',
                '取引日',
                '割引種別',
                '割引前金額',
                '割引額',
                '取引額(税込)',
                '入金状態',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->school_name,
                    $row->student_name,
                    $row->student_code,
                    $row->course_name,
                    $row->attendance_type,
                    $row->account_category_name,
                    $row->scheduled_date,
                    $row->payment_method_name,
                    $row->transaction_date,
                    $row->discount_type_name,
                    $row->before_discount_amount,
                    $row->discount_amount,
                    $row->amount,
                    $row->payment_status_label,
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id'          => ['required', 'exists:students,id'],
            'account_category_id' => ['required', 'exists:account_categories,id'],
            'scheduled_date'      => ['required', 'date'],
            'transaction_date'    => ['nullable', 'date'],
            'payment_method_id'   => ['nullable', 'exists:payment_methods,id'],
            'before_discount_amount' => ['required', 'integer', 'min:0'],
            'discount_amount'        => ['required', 'integer', 'min:0'],
            'payment_status'      => ['required', 'in:paid,unpaid,cancelled'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'discount_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::transaction(function () use ($request) {

            $student = DB::table('students')
                ->where('id', $request->student_id)
                ->first();

            $category = AccountCategory::findOrFail($request->account_category_id);

            $before = (int) $request->before_discount_amount;
            $discount = (int) $request->discount_amount;
            $total = max(0, $before - $discount);

            $invoiceId = DB::table('invoices')->insertGetId([
                'student_id'        => $student->id,
                'payment_method_id' => $request->payment_method_id,
                'invoice_no'        => 'INV-' . now()->format('YmdHis'),
                'billing_year'      => Carbon::parse($request->scheduled_date)->year,
                'billing_month'     => Carbon::parse($request->scheduled_date)->month,
                'issue_date'        => now()->toDateString(),
                'due_date'          => $request->scheduled_date,
                'subtotal'          => $before,
                'discount_amount'   => $discount,
                'tax_amount'        => 0,
                'total_amount'      => $total,
                'payment_status'    => match ($request->payment_status) {
                    'paid' => 'paid',
                    'cancelled' => 'cancelled',
                    default => 'unpaid',
                },
                'paid_at' => $request->payment_status === 'paid' && $request->transaction_date
                    ? Carbon::parse($request->transaction_date)->format('Y-m-d H:i:s')
                    : null,
                'note'       => $request->memo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $invoiceItemId = DB::table('invoice_items')->insertGetId([
                'invoice_id'      => $invoiceId,
                'item_type'       => $category->name === '入会金売上'
                                        ? 'admission'
                                        : 'tuition',
                'item_name'       => $category->name,
                'quantity'        => 1,
                'unit_price'      => $before,
                'amount'          => $before,
                'discount_amount' => $discount,
                'tax_rate'        => 10,
                'note'            => $request->memo,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            if ($request->payment_status === 'paid') {
                $nextPaymentId = ((int) DB::table('payments')->max('id')) + 1;

                DB::table('payments')->insert([
                    'id' => $nextPaymentId,
                    'invoice_id' => $invoiceId,
                    'student_id'        => $student->id,
                    'payment_method_id' => $request->payment_method_id,
                    'payment_date'      => $request->transaction_date,
                    'payment_amount'    => $total,
                    'payment_status'    => 'confirmed',
                    'transaction_no'    => 'PAY-' . now()->format('YmdHis'),
                    'confirmed_by'      => 1,
                    'confirmed_at'      => now(),
                    'note'              => '新規登録',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::table('account_transactions')->insert([
                'scheduled_date'        => $request->scheduled_date,
                'transaction_date'      => $request->payment_status === 'paid'
                                            ? $request->transaction_date
                                            : null,
                'account_category_id'   => $category->id,
                'payment_method_id'     => $request->payment_method_id,
                'transaction_name'      => $category->name,
                'amount'                => $total,
                'before_discount_amount'=> $before,
                'discount_amount'       => $discount,
                'discount_type_id'      => $discount > 0 ? 1 : null,
                'status' => match ($request->payment_status) {
                    'paid' => 'confirmed',
                    'cancelled' => 'cancelled',
                    default => 'planned',
                },
                'memo'                  => $request->memo,
                'discount_note'         => $request->discount_note,
                'school_id'             => $student->school_id,
                'student_id'            => $student->id,
                'source_table'          => 'invoice_item',
                'source_id'             => $invoiceItemId,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.operations.classroom-accounting.tuition-enrollment-sales.index')
            ->with('success', '取引を登録しました。');
    }

    public function searchStudents(Request $request)
    {
        $keyword = trim((string) $request->input('q', ''));

        if (mb_strlen($keyword) < 1) {
            return response()->json([]);
        }

        $students = DB::table('students')
            ->join('schools', 'students.school_id', '=', 'schools.id')
            ->leftJoin('student_course_contracts', function ($join) {
                $join->on('student_course_contracts.student_id', '=', 'students.id')
                    ->where('student_course_contracts.is_active', true)
                    ->where('student_course_contracts.contract_status', 'active');
            })
            ->leftJoin('courses', 'student_course_contracts.course_id', '=', 'courses.id')
            ->leftJoin('course_prices', 'student_course_contracts.course_price_id', '=', 'course_prices.id')
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
                'courses.name as course_name',
                'course_prices.attendance_type',
                'student_course_contracts.monthly_fee',
                
            ])
            ->orderBy('students.student_code')
            ->limit(20)
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'label' => $student->student_code . '｜' . $student->last_name . ' ' . $student->first_name,
                    'student_code' => $student->student_code,
                    'name' => $student->last_name . ' ' . $student->first_name,
                    'school_name' => $student->school_name,
                    'course_name' => $student->course_name,
                    'attendance_type' => $student->attendance_type,
                    'monthly_fee' => $student->monthly_fee,
                ];
            });

        return response()->json($students);
    }

    public function update(Request $request, int $invoiceItemId)
    {
        $validator = Validator::make($request->all(), [
            'scheduled_date' => ['required', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_status' => ['required', 'in:paid,unpaid,cancelled'],
            'before_discount_amount' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['required', 'integer', 'min:0'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'discount_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => '入力内容を確認してください。',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request, $invoiceItemId) {
            $item = DB::table('invoice_items')->where('id', $invoiceItemId)->first();

            if (!$item) {
                abort(404);
            }

            $invoice = DB::table('invoices')->where('id', $item->invoice_id)->first();

            if (!$invoice) {
                abort(404);
            }

            $beforeDiscountAmount = (int) $request->input('before_discount_amount');
            $discountAmount = (int) $request->input('discount_amount');

            DB::table('invoice_items')
                ->where('id', $invoiceItemId)
                ->update([
                    'unit_price' => $beforeDiscountAmount,
                    'amount' => $beforeDiscountAmount,
                    'discount_amount' => $discountAmount,
                    'updated_at' => now(),
                ]);

            $subtotal = (int) DB::table('invoice_items')
                ->where('invoice_id', $invoice->id)
                ->sum('amount');

            $totalDiscount = (int) DB::table('invoice_items')
                ->where('invoice_id', $invoice->id)
                ->sum('discount_amount');

            $totalAmount = max(0, $subtotal - $totalDiscount);

            $paymentStatus = $request->input('payment_status');
            $transactionDate = $request->input('transaction_date');
            $paymentMethodId = $request->input('payment_method_id') ?: $invoice->payment_method_id;

            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update([
                    'payment_method_id' => $paymentMethodId,
                    'due_date' => $request->input('scheduled_date'),
                    'subtotal' => $subtotal,
                    'discount_amount' => $totalDiscount,
                    'total_amount' => $totalAmount,
                    'payment_status' => match ($paymentStatus) {
                        'paid' => 'paid',
                        'cancelled' => 'cancelled',
                        default => 'unpaid',
                    },
                    'paid_at' => $paymentStatus === 'paid' && $transactionDate
                        ? Carbon::parse($transactionDate)->format('Y-m-d H:i:s')
                        : null,
                    'updated_at' => now(),
                ]);

            DB::table('payments')->where('invoice_id', $invoice->id)->delete();

            if ($paymentStatus === 'paid') {
                $nextPaymentId = ((int) DB::table('payments')->max('id')) + 1;

                DB::table('payments')->insert([
                    'id' => $nextPaymentId,
                    'invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'payment_method_id' => $paymentMethodId,
                    'payment_date' => $transactionDate ?: now()->toDateString(),
                    'payment_amount' => $totalAmount,
                    'payment_status' => 'confirmed',
                    'transaction_no' => 'PAY-EDIT-' . $invoice->id . '-' . now()->format('YmdHis'),
                    'confirmed_by' => 1,
                    'confirmed_at' => now(),
                    'note' => '授業料・入会金売上画面から更新',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $categoryId = AccountCategory::where('name', $item->item_type === 'admission' ? '入会金売上' : '授業料売上')->value('id');

            DB::table('account_transactions')
                ->where('source_table', 'invoice_item')
                ->where('source_id', $invoiceItemId)
                ->update([
                    'scheduled_date' => $request->input('scheduled_date'),
                    'transaction_date' => $paymentStatus === 'paid' ? $transactionDate : null,
                    'account_category_id' => $categoryId,
                    'payment_method_id' => $paymentMethodId,
                    'amount' => max(0, $beforeDiscountAmount - $discountAmount),
                    'before_discount_amount' => $beforeDiscountAmount,
                    'discount_amount' => $discountAmount,
                    'discount_type_id' => $discountAmount > 0 ? 1 : null,
                    'status' => match ($paymentStatus) {
                        'paid' => 'confirmed',
                        'cancelled' => 'cancelled',
                        default => 'planned',
                    },
                    'updated_at' => now(),
                    'memo' => $request->input('memo'),
                    'discount_note' => $request->input('discount_note'),
                ]);
        });

        return response()->json([
            'message' => '更新しました。',
        ]);
    }

    private function getBaseRows(): Collection
    {
        $paymentSummary = DB::table('payments')
            ->select(
                'invoice_id',
                DB::raw('MAX(payment_date) as payment_date'),
                DB::raw('MAX(payment_method_id) as payment_method_id'),
                DB::raw('SUM(payment_amount) as paid_amount')
            )
            ->groupBy('invoice_id');

        $discountSummary = DB::table('student_discounts')
            ->leftJoin('discounts', 'student_discounts.discount_id', '=', 'discounts.id')
            ->select(
                'student_discounts.student_id',
                DB::raw('MAX(student_discounts.discount_id) as discount_id'),
                DB::raw('MAX(discounts.name) as discount_name')
            )
            ->where('student_discounts.is_active', true)
            ->groupBy('student_discounts.student_id');

        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('students', 'invoices.student_id', '=', 'students.id')
            ->join('schools', 'students.school_id', '=', 'schools.id')
            ->leftJoin('student_course_contracts', function ($join) {
                $join->on('student_course_contracts.student_id', '=', 'students.id')
                    ->where('student_course_contracts.is_active', true)
                    ->where('student_course_contracts.contract_status', 'active');
            })
            ->leftJoin('courses', 'student_course_contracts.course_id', '=', 'courses.id')
            ->leftJoin('course_prices', 'student_course_contracts.course_price_id', '=', 'course_prices.id')
            ->leftJoinSub($paymentSummary, 'payment_summary', function ($join) {
                $join->on('invoices.id', '=', 'payment_summary.invoice_id');
            })
            ->leftJoinSub($discountSummary, 'discount_summary', function ($join) {
                $join->on('students.id', '=', 'discount_summary.student_id');
            })
            ->leftJoin('payment_methods', function ($join) {
                $join->on(DB::raw('COALESCE(payment_summary.payment_method_id, invoices.payment_method_id)'), '=', 'payment_methods.id');
            })
            ->leftJoin('account_transactions', function ($join) {
                $join->on('account_transactions.source_id', '=', 'invoice_items.id')
                    ->where('account_transactions.source_table', 'invoice_item');
            })
            ->leftJoin('users as created_users', 'account_transactions.created_by', '=', 'created_users.id')
            ->leftJoin('users as updated_users', 'account_transactions.updated_by', '=', 'updated_users.id')
            ->whereIn('invoice_items.item_type', ['tuition', 'admission'])
            ->select([
                'invoice_items.id as id',
                'invoice_items.invoice_id',
                'invoice_items.item_type',
                'invoice_items.item_name',
                'invoice_items.amount as item_amount',
                'invoice_items.discount_amount as item_discount_amount',
                'invoice_items.note as item_note',

                'invoices.student_id',
                'invoices.payment_method_id as invoice_payment_method_id',
                'invoices.due_date',
                'invoices.paid_at',
                'invoices.payment_status',
                'invoices.note as invoice_note',

                'students.school_id',
                'students.student_code',
                'students.last_name',
                'students.first_name',

                'schools.name as school_name',
                'courses.name as course_name',
                'course_prices.attendance_type',

                'payment_summary.payment_date',
                'payment_summary.payment_method_id as payment_payment_method_id',
                'payment_methods.name as payment_method_name',

                'discount_summary.discount_id',
                'discount_summary.discount_name',

                'invoice_items.created_at',
                'invoice_items.updated_at',

                'account_transactions.id as account_transaction_id',
                'account_transactions.memo as transaction_note',
                'account_transactions.discount_note',
                'account_transactions.created_at as transaction_created_at',
                'account_transactions.updated_at as transaction_updated_at',
                'created_users.name as created_by_name',
                'updated_users.name as updated_by_name',

            ])
            ->orderByDesc('invoice_items.id')
            ->get()
            ->map(function ($row) {
                $categoryName = $row->item_type === 'admission'
                    ? '入会金売上'
                    : '授業料売上';

                $categoryId = AccountCategory::where('name', $categoryName)->value('id');

                $beforeDiscount = (int) $row->item_amount;
                $discountAmount = (int) ($row->item_discount_amount ?? 0);
                $amount = max(0, $beforeDiscount - $discountAmount);

                return (object) [
                    'id' => (int) $row->id,
                    'invoice_id' => (int) $row->invoice_id,
                    'student_id' => (int) $row->student_id,
                    'school_id' => (int) $row->school_id,
                    'payment_method_id' => $row->payment_payment_method_id ?: $row->invoice_payment_method_id,

                    'scheduled_date' => $row->due_date,
                    'transaction_date' => $row->payment_date ?: $row->paid_at,

                    'account_category_id' => $categoryId,
                    'account_category_name' => $categoryName,
                    'transaction_name' => $row->item_name,

                    'school_name' => $row->school_name ?? '-',
                    'student_name' => trim(($row->last_name ?? '') . ' ' . ($row->first_name ?? '')) ?: '-',
                    'student_code' => $row->student_code ?? '-',

                    'course_name' => $row->course_name ?? '未設定コース',
                    'attendance_type' => $row->item_type === 'admission'
                        ? '-'
                        : ($row->attendance_type ?? '未設定'),

                    'discount_type_id' => $discountAmount > 0 ? ($row->discount_id ?? null) : null,
                    'discount_type_name' => $discountAmount > 0 ? ($row->discount_name ?? '割引あり') : '-',
                    'discount_amount' => $discountAmount,
                    'before_discount_amount' => $beforeDiscount,
                    'amount' => $amount,

                    'payment_status' => $this->normalizePaymentStatus($row->payment_status),
                    'payment_status_label' => $this->paymentStatusLabel($row->payment_status),

                    'payment_method_name' => $row->payment_method_name ?? '-',
                    'transaction_note' => $row->transaction_note ?? '-',
                    'discount_note' => $row->discount_note ?? '-',

                    'created_at' => $row->created_at,

                    'updated_at' => $row->transaction_updated_at,

                    'created_by_name' => $row->created_by_name ?? '-',

                    'updated_by_name' => $row->updated_by_name ?? '-',

                    'account_transaction_id' => $row->account_transaction_id,
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
            ->when($request->filled('account_category_id'), fn ($c) => $c->where('account_category_id', (int) $request->account_category_id))
            ->when($request->filled('payment_method_id'), fn ($c) => $c->where('payment_method_id', (int) $request->payment_method_id))
            ->when($request->filled('discount_type_id'), fn ($c) => $c->where('discount_type_id', (int) $request->discount_type_id))
            ->when($request->filled('payment_status'), fn ($c) => $c->where('payment_status', $request->payment_status))
            ->when($request->filled('course_name'), fn ($c) => $c->where('course_name', $request->course_name))
            ->when($request->filled('attendance_type'), fn ($c) => $c->where('attendance_type', $request->attendance_type))
            ->when($request->filled('keyword'), function ($c) use ($request) {
                $keyword = trim($request->keyword);

                return $c->filter(fn ($r) =>
                    str_contains($r->transaction_name, $keyword)
                    || str_contains($r->student_name, $keyword)
                    || str_contains($r->student_code, $keyword)
                    || str_contains($r->course_name, $keyword)
                    || str_contains($r->transaction_note ?? '', $keyword)
                    || str_contains($r->discount_note ?? '', $keyword)
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
            'course_name' => 'course_name',
            'attendance_type' => 'attendance_type',
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

    private function buildTuitionSalesChartData(Collection $rows, string $period): Collection
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

        $labels = collect(range(0, $start->diffInMonths($now)))
            ->map(fn ($i) => $start->copy()->addMonths($i)->format('Y/m'))
            ->values();

        $tuitionRows = $rows->filter(fn ($r) => $r->account_category_name === '授業料売上');

        $courseNames = $tuitionRows->pluck('course_name')->unique()->values();

        return collect([
            'labels' => $labels,
            'datasets' => $courseNames->map(function ($courseName) use ($labels, $tuitionRows) {
                return [
                    'label' => $courseName,
                    'data' => $labels->map(function ($label) use ($tuitionRows, $courseName) {
                        return $tuitionRows
                            ->filter(function ($row) use ($label, $courseName) {
                                $date = $row->transaction_date ?: $row->scheduled_date;

                                return $date
                                    && Carbon::parse($date)->format('Y/m') === $label
                                    && $row->course_name === $courseName;
                            })
                            ->sum('amount');
                    })->values(),
                ];
            })->values(),

            'collectionRates' => $labels->map(function ($label) use ($tuitionRows) {
                $monthRows = $tuitionRows
                    ->filter(function ($row) use ($label) {
                        $date = $row->transaction_date ?: $row->scheduled_date;
                        return $date && Carbon::parse($date)->format('Y/m') === $label;
                    });

                $salesAmount = $monthRows->sum('amount');
                $paidAmount = $monthRows->where('payment_status', 'paid')->sum('amount');

                return $salesAmount > 0
                    ? round(($paidAmount / $salesAmount) * 100, 1)
                    : 0;
            })->values(),
        ]);
    }

    private function buildCourseCompositionData(Collection $rows): Collection
    {
        $currentMonth = Carbon::now()->format('Y/m');

        $targetRows = $rows
            ->filter(fn ($r) => $r->account_category_name === '授業料売上')
            ->filter(function ($row) use ($currentMonth) {
                $date = $row->transaction_date ?: $row->scheduled_date;
                return $date && Carbon::parse($date)->format('Y/m') === $currentMonth;
            });

        $totalAmount = $targetRows->sum('amount');

        $courseRows = $targetRows
            ->groupBy('course_name')
            ->map(function ($items, $courseName) use ($totalAmount) {
                $amount = $items->sum('amount');

                return [
                    'label' => $courseName,
                    'amount' => $amount,
                    'rate' => $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return collect([
            'labels' => $courseRows->pluck('label')->values(),
            'data' => $courseRows->pluck('amount')->values(),
            'rows' => $courseRows,
            'totalAmount' => $totalAmount,
            'recordCount' => $targetRows->count(),
            'monthlyAverage' => $targetRows->count() > 0 ? round($totalAmount / 3) : 0,
            'studentCount' => $targetRows->pluck('student_id')->unique()->count(),
        ]);
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