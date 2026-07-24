<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ショップ注文情報管理。
 *
 * 関連画面: 運営 ＞ 教室会計 ＞ 注文情報
 * 関連Service: なし（既存ショップ売上との互換性を優先しQuery Builderで完結）
 * 利用DB: shop_orders / shop_order_items / students / schools / payment_methods /
 *         shop_pickups / account_transactions / shop_products（参照、一部更新）
 * 役割: 注文単位の検索、詳細、支払・受渡更新、取消、CSV出力。
 */
class ShopOrderController extends Controller
{
    /** 注文一覧を表示する。 */
    public function index(Request $request): View
    {
        $query = $this->baseQuery($request);
        $orders = $query->orderByDesc('o.ordered_at')->orderByDesc('o.id')->paginate(30)->withQueryString();
        $schools = DB::table('schools')->orderBy('id')->get(['id', 'name']);
        $paymentMethods = DB::table('payment_methods')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);
        $summaryQuery = $this->baseQuery($request, false);
        $summary = [
            'count' => (clone $summaryQuery)->count(),
            'amount' => (int) (clone $summaryQuery)->where('o.order_status', '!=', 'cancelled')->sum('o.total_amount'),
            'unpaid' => (clone $summaryQuery)->where('o.order_status', '!=', 'cancelled')->where('o.payment_status', 'unpaid')->count(),
            'undelivered' => (clone $summaryQuery)->where('o.order_status', '!=', 'cancelled')->whereNotIn('o.delivery_status', ['delivered', 'cancelled'])->count(),
        ];

        return view('admin.account.shop-orders.index', compact('orders', 'schools', 'paymentMethods', 'summary'));
    }

    /** 注文詳細を表示する。 */
    public function show(Request $request, int $order): View
    {
        $record = $this->baseQuery(new Request())->where('o.id', $order)->first();
        abort_if(!$record, 404);

        $items = DB::table('shop_order_items as oi')
            ->leftJoin('shop_products as p', 'oi.shop_product_id', '=', 'p.id')
            ->where('oi.shop_order_id', $order)
            ->orderBy('oi.id')
            ->select('oi.*', 'p.product_code as current_product_code')
            ->get();
        $pickup = DB::table('shop_pickups as sp')
            ->leftJoin('users as handler', 'sp.handled_by', '=', 'handler.id')
            ->where('sp.shop_order_id', $order)
            ->select('sp.*', 'handler.name as handled_by_name')
            ->first();
        $accountTransaction = DB::table('account_transactions')
            ->whereIn('source_table', ['shop_order', 'shop_orders'])->where('source_id', $order)->first();
        $paymentMethods = DB::table('payment_methods')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);

        return view('admin.account.shop-orders.show', compact('record', 'items', 'pickup', 'accountTransaction', 'paymentMethods'));
    }

    /** 支払・受渡・注文基本情報を更新する。金額と明細はショップ売上画面のみで変更する。 */
    public function update(Request $request, int $order): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_status' => ['required', Rule::in(['unpaid', 'paid'])],
            'order_status' => ['required', Rule::in(['ordered', 'processing', 'completed'])],
            'delivery_method' => ['required', Rule::in(['classroom', 'shipping', 'other'])],
            'delivery_status' => ['required', Rule::in(['pending', 'preparing', 'ready', 'delivered'])],
            'scheduled_date' => ['nullable', 'date'],
            'transaction_date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $order): void {
            $current = DB::table('shop_orders')->where('id', $order)->lockForUpdate()->first();
            abort_if(!$current, 404);
            abort_if($current->order_status === 'cancelled', 422, '取消済み注文は更新できません。');

            $now = now();
            $transactionDate = $validated['payment_status'] === 'paid'
                ? ($validated['transaction_date'] ?: now()->toDateString()) : null;
            $deliveredAt = $validated['delivery_status'] === 'delivered'
                ? ($current->delivered_at ?: $now) : null;

            DB::table('shop_orders')->where('id', $order)->update([
                ...$validated,
                'transaction_date' => $transactionDate,
                'delivered_at' => $deliveredAt,
                'updated_by' => auth()->id() ?? 1,
                'updated_at' => $now,
            ]);

            DB::table('account_transactions')->whereIn('source_table', ['shop_order', 'shop_orders'])->where('source_id', $order)->update([
                'scheduled_date' => $validated['scheduled_date'],
                'transaction_date' => $transactionDate,
                'payment_method_id' => $validated['payment_method_id'],
                'status' => $validated['payment_status'] === 'paid' ? 'confirmed' : 'planned',
                'memo' => $validated['memo'],
                'updated_by' => auth()->id() ?? 1,
                'updated_at' => $now,
            ]);

            if ($validated['delivery_method'] === 'classroom') {
                DB::table('shop_pickups')->updateOrInsert(
                    ['shop_order_id' => $order],
                    [
                        'user_id' => $current->created_by ?: (auth()->id() ?? 1),
                        'pickup_school_id' => $current->school_id,
                        'pickup_status' => $validated['delivery_status'],
                        'pickup_scheduled_date' => $validated['scheduled_date'] ?: now()->toDateString(),
                        'picked_up_at' => $validated['delivery_status'] === 'delivered' ? $deliveredAt : null,
                        'handled_by' => $validated['delivery_status'] === 'delivered' ? (auth()->id() ?? 1) : null,
                        'note' => $validated['memo'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        });

        return back()->with('success', '注文情報を更新しました。');
    }

    /** 注文を一度だけ取消し、会計取引と受渡状態を整合させる。 */
    public function cancel(Request $request, int $order): RedirectResponse
    {
        $validated = $request->validate(['cancel_reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($validated, $order): void {
            $current = DB::table('shop_orders')->where('id', $order)->lockForUpdate()->first();
            abort_if(!$current, 404);
            if ($current->order_status === 'cancelled') {
                abort(422, 'この注文は既に取消済みです。');
            }

            // 現行のショップ売上登録は在庫を減算していないため、取消時も在庫は変更しない。
            // 将来、販売時在庫減算を導入する際は同一トランザクションで対になる復元処理を追加する。

            DB::table('shop_orders')->where('id', $order)->update([
                'order_status' => 'cancelled',
                'payment_status' => 'cancelled',
                'delivery_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['cancel_reason'],
                'updated_by' => auth()->id() ?? 1,
                'updated_at' => now(),
            ]);
            DB::table('shop_pickups')->where('shop_order_id', $order)->update(['pickup_status' => 'cancelled', 'updated_at' => now()]);
            DB::table('account_transactions')->whereIn('source_table', ['shop_order', 'shop_orders'])->where('source_id', $order)->update([
                'status' => 'cancelled', 'cancelled_reason' => $validated['cancel_reason'],
                'updated_by' => auth()->id() ?? 1, 'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.operations.classroom-accounting.shop-orders.index')->with('success', '注文を取り消しました。会計・受渡状態も取消済みに更新しました。');
    }

    /** 現在の検索条件と同じ注文一覧をCSV出力する。 */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->baseQuery($request)->orderByDesc('o.ordered_at')->get();
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['注文ID','注文番号','注文日時','教室','生徒コード','生徒名','購入者名','商品概要','商品点数','数量','割引前','割引','送料','税額','請求金額','支払方法','支払状態','注文状態','受渡方法','受渡状態','予定日','取引日']);
            foreach ($rows as $row) {
                $paymentLabels = ['paid' => '入金済', 'unpaid' => '未入金', 'cancelled' => '取消済み'];
                $orderLabels = ['ordered' => '注文済', 'processing' => '処理中', 'completed' => '完了', 'cancelled' => '取消済み'];
                $deliveryMethodLabels = ['classroom' => '教室受取', 'shipping' => '配送', 'other' => 'その他'];
                $deliveryLabels = ['pending' => '未準備', 'preparing' => '準備中', 'ready' => '受渡可能', 'delivered' => '受渡完了', 'cancelled' => '取消済み'];
                fputcsv($out, [$row->id,$row->order_no,$row->ordered_at,$row->school_name,$row->student_code,$row->student_name,$row->buyer_name,$row->item_summary,$row->item_count,$row->total_quantity,$row->subtotal_amount,$row->discount_amount,$row->shipping_fee,$row->tax_amount,$row->total_amount,$row->payment_method_name,$paymentLabels[$row->payment_status] ?? '未設定',$orderLabels[$row->order_status] ?? '未設定',$deliveryMethodLabels[$row->delivery_method] ?? '未設定',$deliveryLabels[$row->delivery_status] ?? '未設定',$row->scheduled_date,$row->transaction_date]);
            }
            fclose($out);
        }, 'shop-orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** 検索条件を共有する注文集約クエリ。 */
    private function baseQuery(Request $request, bool $withSelect = true)
    {
        $items = DB::table('shop_order_items')
            ->select('shop_order_id')
            ->selectRaw('COUNT(*) as item_count')
            ->selectRaw('COALESCE(SUM(quantity),0) as total_quantity')
            ->selectRaw("STRING_AGG(product_name, '、' ORDER BY id) as item_summary")
            ->groupBy('shop_order_id');

        $q = DB::table('shop_orders as o')
            ->join('students as s', 'o.student_id', '=', 's.id')
            ->join('schools as sc', 'o.school_id', '=', 'sc.id')
            ->leftJoin('payment_methods as pm', 'o.payment_method_id', '=', 'pm.id')
            ->leftJoinSub($items, 'it', 'it.shop_order_id', '=', 'o.id');

        if ($withSelect) {
            $q->select('o.*', 'sc.name as school_name', 's.student_code',
                DB::raw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name"),
                'pm.name as payment_method_name', DB::raw('COALESCE(it.item_count,0) as item_count'),
                DB::raw('COALESCE(it.total_quantity,0) as total_quantity'), 'it.item_summary');
        }

        $keyword = trim((string) $request->input('keyword'));
        if ($keyword !== '') {
            $q->where(function ($w) use ($keyword): void {
                $w->where('o.order_no', 'like', "%{$keyword}%")
                    ->orWhere('s.student_code', 'like', "%{$keyword}%")
                    ->orWhere('s.last_name', 'like', "%{$keyword}%")
                    ->orWhere('s.first_name', 'like', "%{$keyword}%")
                    ->orWhere('o.buyer_name', 'like', "%{$keyword}%")
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('shop_order_items as si')->whereColumn('si.shop_order_id', 'o.id')->where('si.product_name', 'like', "%{$keyword}%"));
            });
        }
        foreach ([['ordered_from','o.ordered_at','>='],['scheduled_from','o.scheduled_date','>='],['scheduled_to','o.scheduled_date','<='],['transaction_from','o.transaction_date','>='],['transaction_to','o.transaction_date','<=']] as [$input,$column,$op]) {
            if ($request->filled($input)) {
                $q->where($column, $op, $request->input($input));
            }
        }
        if ($request->filled('ordered_to')) {
            $q->where('o.ordered_at', '<', \Carbon\Carbon::parse($request->input('ordered_to'))->addDay()->startOfDay());
        }
        foreach (['school_id'=>'o.school_id','payment_method_id'=>'o.payment_method_id','payment_status'=>'o.payment_status','order_status'=>'o.order_status','delivery_method'=>'o.delivery_method','delivery_status'=>'o.delivery_status'] as $input => $column) {
            if ($request->filled($input)) $q->where($column, $request->input($input));
        }
        if ($request->filled('amount_min')) $q->where('o.total_amount', '>=', (int) $request->input('amount_min'));
        if ($request->filled('amount_max')) $q->where('o.total_amount', '<=', (int) $request->input('amount_max'));
        if ($request->boolean('unpaid_only')) $q->where('o.payment_status', 'unpaid');
        if ($request->boolean('undelivered_only')) $q->whereNotIn('o.delivery_status', ['delivered','cancelled']);
        return $q;
    }
}
