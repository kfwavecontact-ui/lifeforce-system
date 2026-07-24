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

class ShopSaleController extends Controller
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

        return view('admin.account.shop-sales.index', [
            'sales' => $sales,
            'summary' => $this->buildSummary($filteredRows),
            'shopSalesChartData' => $this->buildSalesChartData($filteredRows, $chartPeriod),
            'productCompositionData' => $this->buildProductCompositionData($filteredRows),
            'chartPeriod' => $chartPeriod,

            'students' => DB::table('students')
                ->select('id', 'student_code', 'last_name', 'first_name')
                ->orderBy('id')
                ->get(),

            'schools' => School::orderBy('name')->get(),

            'accountCategories' => AccountCategory::where('transaction_type', '収益')
                ->where('name', 'ショップ売上')
                ->orderBy('sort_order')
                ->get(),

            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(),

            'shopProducts' => DB::table('shop_products')
                ->select('id', 'name', 'product_code', 'price', 'tax_rate', 'stock_quantity')
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('sales_end_at')->orWhere('sales_end_at', '>=', now());
                })
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),

            'discounts' => Discount::orderBy('sort_order')->get(),

            'courseNames' => collect(),
            'attendanceTypes' => collect(),

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

        $filename = 'shop_sales_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ID',
                '注文No',
                '教室',
                '生徒',
                '生徒コード',
                '商品名',
                '商品数',
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
                    $row->order_no,
                    $row->school_name,
                    $row->student_name,
                    $row->student_code,
                    $row->item_summary,
                    $row->total_quantity,
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
            'scheduled_date' => ['required', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'payment_status' => ['required', 'in:paid,unpaid,cancelled'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.shop_product_id' => ['required', 'exists:shop_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_amount' => ['required', 'integer', 'min:0'],
            'memo' => ['nullable', 'string', 'max:1000'],
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

            $category = AccountCategory::where('name', 'ショップ売上')->firstOrFail();

            $subtotalAmount = 0;
            $discountAmount = 0;
            $taxAmount = 0;
            $totalAmount = 0;

            $orderId = DB::table('shop_orders')->insertGetId([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'order_no' => $this->generateOrderNo(),
                'order_status' => $request->payment_status === 'cancelled' ? 'cancelled' : 'ordered',
                'payment_status' => $request->payment_status,
                'payment_method_id' => $request->payment_method_id,
                'ordered_at' => now(),
                'scheduled_date' => $request->scheduled_date,
                'transaction_date' => $request->payment_status === 'paid'
                    ? ($request->transaction_date ?: now()->toDateString())
                    : null,
                'subtotal_amount' => 0,
                'discount_amount' => 0,
                'coupon_discount' => 0,
                'point_discount' => 0,
                'campaign_discount' => 0,
                'shipping_fee' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'buyer_name' => trim(($student->last_name ?? '') . ' ' . ($student->first_name ?? '')),
                'buyer_email' => null,
                'buyer_phone' => null,
                'delivery_method' => 'classroom',
                'delivery_status' => 'pending',
                'delivered_at' => null,
                'cancelled_at' => $request->payment_status === 'cancelled' ? now() : null,
                'cancel_reason' => null,
                'memo' => $request->memo,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                $product = DB::table('shop_products')
                    ->where('id', $item['shop_product_id'])
                    ->first();

                $quantity = (int) $item['quantity'];
                $unitPrice = (int) $product->price;
                $purchasePrice = (int) ($product->purchase_price ?? 0);
                $itemSubtotal = $unitPrice * $quantity;
                $itemDiscount = min((int) ($item['discount_amount'] ?? 0), $itemSubtotal);
                $itemTotal = max(0, $itemSubtotal - $itemDiscount);
                $taxRate = (int) ($product->tax_rate ?? 10);
                $itemTax = (int) floor($itemTotal * $taxRate / (100 + $taxRate));

                DB::table('shop_order_items')->insert([
                    'shop_order_id' => $orderId,
                    'shop_product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->product_code,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'purchase_price' => $purchasePrice,
                    'discount_amount' => $itemDiscount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $itemTax,
                    'subtotal_amount' => $itemSubtotal,
                    'total_amount' => $itemTotal,
                    'point_reward' => (int) ($product->point_reward ?? 0) * $quantity,
                    'point_used' => 0,
                    'memo' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $subtotalAmount += $itemSubtotal;
                $discountAmount += $itemDiscount;
                $taxAmount += $itemTax;
                $totalAmount += $itemTotal;
            }

            DB::table('shop_orders')
                ->where('id', $orderId)
                ->update([
                    'subtotal_amount' => $subtotalAmount,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'updated_at' => now(),
                ]);

            DB::table('account_transactions')->insert([
                'scheduled_date' => $request->scheduled_date,
                'transaction_date' => $request->payment_status === 'paid'
                    ? ($request->transaction_date ?: now()->toDateString())
                    : null,
                'account_category_id' => $category->id,
                'payment_method_id' => $request->payment_method_id,
                'transaction_name' => 'ショップ売上',
                'amount' => $totalAmount,
                'before_discount_amount' => $subtotalAmount,
                'discount_amount' => $discountAmount,
                'discount_type_id' => null,
                'status' => match ($request->payment_status) {
                    'paid' => 'confirmed',
                    'cancelled' => 'cancelled',
                    default => 'planned',
                },
                'memo' => $request->memo,
                'discount_note' => $discountAmount > 0 ? 'ショップ売上割引' : null,
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'source_table' => 'shop_order',
                'source_id' => $orderId,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.operations.classroom-accounting.shop-sales.index')
            ->with('success', 'ショップ売上を登録しました。');
    }

    public function update(Request $request, int $orderId)
    {
        $validator = Validator::make($request->all(), [
            'scheduled_date' => ['required', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_status' => ['required', 'in:paid,unpaid,cancelled'],
            'before_discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'discount_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => '入力内容を確認してください。',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request, $orderId) {
            $order = DB::table('shop_orders')->where('id', $orderId)->first();

            if (!$order) {
                abort(404);
            }

            $beforeDiscountAmount = (int) $request->input('before_discount_amount', $order->subtotal_amount);
            $discountAmount = min((int) $request->input('discount_amount', $order->discount_amount), $beforeDiscountAmount);
            $totalAmount = max(0, $beforeDiscountAmount - $discountAmount);

            DB::table('shop_orders')
                ->where('id', $orderId)
                ->update([
                    'scheduled_date' => $request->input('scheduled_date'),
                    'transaction_date' => $request->input('payment_status') === 'paid'
                        ? ($request->input('transaction_date') ?: now()->toDateString())
                        : null,
                    'payment_method_id' => $request->input('payment_method_id'),
                    'payment_status' => $request->input('payment_status'),
                    'order_status' => $request->input('payment_status') === 'cancelled' ? 'cancelled' : 'ordered',
                    'cancelled_at' => $request->input('payment_status') === 'cancelled' ? now() : null,
                    'subtotal_amount' => $beforeDiscountAmount,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $totalAmount,
                    'memo' => $request->input('memo'),
                    'updated_by' => auth()->id() ?? 1,
                    'updated_at' => now(),
                ]);

            DB::table('account_transactions')
                ->where('source_table', 'shop_order')
                ->where('source_id', $orderId)
                ->update([
                    'scheduled_date' => $request->input('scheduled_date'),
                    'transaction_date' => $request->input('payment_status') === 'paid'
                        ? ($request->input('transaction_date') ?: now()->toDateString())
                        : null,
                    'payment_method_id' => $request->input('payment_method_id'),
                    'status' => match ($request->input('payment_status')) {
                        'paid' => 'confirmed',
                        'cancelled' => 'cancelled',
                        default => 'planned',
                    },
                    'before_discount_amount' => $beforeDiscountAmount,
                    'discount_amount' => $discountAmount,
                    'amount' => $totalAmount,
                    'memo' => $request->input('memo'),
                    'discount_note' => $request->input('discount_note'),
                    'updated_by' => auth()->id() ?? 1,
                    'updated_at' => now(),
                ]);
        });

        return response()->json([
            'message' => 'ショップ売上を更新しました。',
        ]);
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
                $query->where('students.student_code', 'ILIKE', "%{$keyword}%")
                    ->orWhere('students.last_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('students.first_name', 'ILIKE', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(students.last_name, students.first_name) ILIKE ?", ["%{$keyword}%"])
                    ->orWhereRaw("CONCAT(students.last_name, ' ', students.first_name) ILIKE ?", ["%{$keyword}%"]);
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

    public function searchProducts(Request $request)
    {
        $keyword = trim((string) $request->input('keyword', ''));

        if ($keyword === '') {
            return response()->json([]);
        }

        $products = DB::table('shop_products')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('sales_end_at')->orWhere('sales_end_at', '>=', now());
            })
            ->where(function ($query) use ($keyword) {
                $query->where('name', 'ILIKE', '%' . $keyword . '%')
                    ->orWhere('product_code', 'ILIKE', '%' . $keyword . '%');
            })
            ->orderBy('display_order')
            ->orderBy('id')
            ->limit(10)
            ->get([
                'id',
                'product_code',
                'name',
                'price',
                'stock_quantity',
                'tax_rate',
            ]);

        return response()->json($products);
    }

    private function getBaseRows(): Collection
    {
        $category = AccountCategory::where('name', 'ショップ売上')->first();

        return DB::table('shop_orders')
            ->join('students', 'shop_orders.student_id', '=', 'students.id')
            ->join('schools', 'shop_orders.school_id', '=', 'schools.id')
            ->leftJoin('payment_methods', 'shop_orders.payment_method_id', '=', 'payment_methods.id')
            ->leftJoin('account_transactions', function ($join) {
                $join->on('account_transactions.source_id', '=', 'shop_orders.id')
                    ->where('account_transactions.source_table', 'shop_order');
            })
            ->leftJoin('users as created_users', 'shop_orders.created_by', '=', 'created_users.id')
            ->leftJoin('users as updated_users', 'shop_orders.updated_by', '=', 'updated_users.id')
            ->select([
                'shop_orders.id',
                'shop_orders.order_no',
                'shop_orders.school_id',
                'shop_orders.student_id',
                'shop_orders.payment_method_id',
                'shop_orders.ordered_at',
                'shop_orders.scheduled_date',
                'shop_orders.transaction_date',
                'shop_orders.subtotal_amount',
                'shop_orders.discount_amount',
                'shop_orders.tax_amount',
                'shop_orders.total_amount',
                'shop_orders.payment_status',
                'shop_orders.order_status',
                'shop_orders.delivery_method',
                'shop_orders.delivery_status',
                'shop_orders.memo',
                'shop_orders.created_at',
                'shop_orders.updated_at',
                'students.student_code',
                'students.last_name',
                'students.first_name',
                'schools.name as school_name',
                'payment_methods.name as payment_method_name',
                'account_transactions.id as account_transaction_id',
                'account_transactions.memo as transaction_note',
                'account_transactions.discount_note',
                'created_users.name as created_by_name',
                'updated_users.name as updated_by_name',
            ])
            ->orderByDesc('shop_orders.id')
            ->get()
            ->map(function ($row) use ($category) {
                $items = DB::table('shop_order_items')
                    ->where('shop_order_id', $row->id)
                    ->orderBy('id')
                    ->get();

                $itemNames = $items
                    ->pluck('product_name')
                    ->filter()
                    ->values();

                if ($itemNames->count() === 0) {
                    $itemSummary = '-';
                } elseif ($itemNames->count() === 1) {
                    $itemSummary = $itemNames->get(0);
                } elseif ($itemNames->count() === 2) {
                    $itemSummary = $itemNames->take(2)->implode('、');
                } else {
                    $itemSummary = $itemNames->take(2)->implode('、') . ' 他' . ($itemNames->count() - 2) . '件';
                }

                $itemDetailSummary = $items->map(function ($item) {
                    return $item->product_name . ' ×' . $item->quantity;
                })->implode("\n");

                $itemsForDetail = $items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'sku' => $item->sku,
                        'quantity' => (int) $item->quantity,
                        'unit_price' => (int) $item->unit_price,
                        'discount_amount' => (int) $item->discount_amount,
                        'subtotal_amount' => (int) $item->subtotal_amount,
                        'total_amount' => (int) $item->total_amount,
                        'tax_rate' => (int) $item->tax_rate,
                        'tax_amount' => (int) $item->tax_amount,
                    ];
                })->values();

                return (object) [
                    'id' => (int) $row->id,
                    'invoice_id' => null,
                    'order_no' => $row->order_no,

                    'student_id' => (int) $row->student_id,
                    'school_id' => (int) $row->school_id,
                    'payment_method_id' => $row->payment_method_id,

                    'scheduled_date' => $row->scheduled_date,
                    'transaction_date' => $row->transaction_date,

                    'account_category_id' => $category?->id,
                    'account_category_name' => 'ショップ売上',
                    'transaction_name' => $itemDetailSummary ?: 'ショップ売上',

                    'school_name' => $row->school_name ?? '-',
                    'student_name' => trim(($row->last_name ?? '') . ' ' . ($row->first_name ?? '')) ?: '-',
                    'student_code' => $row->student_code ?? '-',

                    'course_name' => '-',
                    'attendance_type' => '-',

                    'discount_type_id' => ((int) $row->discount_amount > 0) ? 1 : null,
                    'discount_type_name' => ((int) $row->discount_amount > 0) ? '割引あり' : '-',
                    'discount_amount' => (int) $row->discount_amount,
                    'before_discount_amount' => (int) $row->subtotal_amount,
                    'amount' => (int) $row->total_amount,

                    'payment_status' => $this->normalizePaymentStatus($row->payment_status),
                    'payment_status_label' => $this->paymentStatusLabel($row->payment_status),

                    'payment_method_name' => $row->payment_method_name ?? '-',
                    'transaction_note' => $row->transaction_note ?? $row->memo ?? '-',
                    'discount_note' => $row->discount_note ?? '-',

                    'order_status' => $row->order_status,
                    'delivery_method' => $row->delivery_method,
                    'delivery_status' => $row->delivery_status,

                    'items' => $items,
                    'items_for_detail' => $itemsForDetail,
                    'item_summary' => $itemSummary,
                    'item_detail_summary' => $itemDetailSummary ?: '-',
                    'item_count' => $items->count(),
                    'total_quantity' => (int) $items->sum('quantity'),

                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,

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
            ->when($request->filled('keyword'), function ($c) use ($request) {
                $keyword = trim((string) $request->keyword);

                return $c->filter(fn ($r) =>
                    str_contains($r->order_no ?? '', $keyword)
                    || str_contains($r->transaction_name ?? '', $keyword)
                    || str_contains($r->item_summary ?? '', $keyword)
                    || str_contains($r->item_detail_summary ?? '', $keyword)
                    || str_contains($r->student_name ?? '', $keyword)
                    || str_contains($r->student_code ?? '', $keyword)
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
            'order_no' => 'order_no',
            'school' => 'school_name',
            'student' => 'student_name',
            'student_code' => 'student_code',
            'item_summary' => 'item_summary',
            'total_quantity' => 'total_quantity',
            'account_category' => 'account_category_name',
            'scheduled_date' => 'scheduled_date',
            'payment_method' => 'payment_method_name',
            'transaction_date' => 'transaction_date',
            'discount_type' => 'discount_type_name',
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

    private function buildSalesChartData(Collection $rows, string $period): Collection
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

        $salesAmounts = $labels->map(function ($label) use ($rows) {
            return $rows
                ->filter(function ($row) use ($label) {
                    $date = $row->transaction_date ?: $row->scheduled_date;
                    return $date && Carbon::parse($date)->format('Y/m') === $label;
                })
                ->sum('amount');
        })->values();

        $salesQuantities = $labels->map(function ($label) use ($rows) {
            return $rows
                ->filter(function ($row) use ($label) {
                    $date = $row->transaction_date ?: $row->scheduled_date;
                    return $date && Carbon::parse($date)->format('Y/m') === $label;
                })
                ->sum('total_quantity');
        })->values();

        return collect([
            'labels' => $labels,
            'salesAmounts' => $salesAmounts,
            'salesQuantities' => $salesQuantities,
        ]);
    }

    private function buildProductCompositionData(Collection $rows): Collection
    {
        $currentMonth = Carbon::now()->format('Y/m');

        $targetRows = $rows->filter(function ($row) use ($currentMonth) {
            $date = $row->transaction_date ?: $row->scheduled_date;
            return $date && Carbon::parse($date)->format('Y/m') === $currentMonth;
        });

        $expandedItems = $targetRows->flatMap(function ($row) {
            return collect($row->items_for_detail ?? [])->map(function ($item) {
                return [
                    'label' => $item['product_name'],
                    'amount' => $item['total_amount'],
                    'quantity' => $item['quantity'],
                ];
            });
        });

        $totalAmount = $expandedItems->sum('amount');

        $productRows = $expandedItems
            ->groupBy('label')
            ->map(function ($items, $label) use ($totalAmount) {
                $amount = $items->sum('amount');

                return [
                    'label' => $label,
                    'amount' => $amount,
                    'quantity' => $items->sum('quantity'),
                    'rate' => $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return collect([
            'labels' => $productRows->pluck('label')->values(),
            'data' => $productRows->pluck('amount')->values(),
            'rows' => $productRows,
            'totalAmount' => $totalAmount,
            'recordCount' => $targetRows->count(),
            'monthlyAverage' => $targetRows->count() > 0 ? round($totalAmount / max(1, $targetRows->count())) : 0,
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

    private function generateOrderNo(): string
    {
        return 'SHOP-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}