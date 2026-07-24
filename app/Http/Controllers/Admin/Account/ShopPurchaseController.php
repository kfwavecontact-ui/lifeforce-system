<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ショップ購入情報（注文明細）照会。
 *
 * 関連画面: 運営 ＞ 教室会計 ＞ 購入情報
 * 利用DB: shop_order_items / shop_orders / shop_products / shop_categories /
 *         students / schools / payment_methods（参照のみ）
 * 役割: 誰が何を何個購入したかを明細単位で検索・集計・CSV出力する。
 */
class ShopPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $base = $this->baseQuery($request);
        $summaryBase = clone $base;
        $summary = $summaryBase->selectRaw('COALESCE(SUM(oi.quantity),0) as quantity, COALESCE(SUM(oi.total_amount),0) as sales, COALESCE(SUM(oi.purchase_price * oi.quantity),0) as cost, COALESCE(SUM(oi.total_amount - (oi.purchase_price * oi.quantity)),0) as profit')->first();
        $sort = $request->string('sort')->toString() ?: 'ordered_at';
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['ordered_at' => 'o.ordered_at', 'quantity' => 'oi.quantity', 'total_amount' => 'oi.total_amount', 'profit' => DB::raw('(oi.total_amount - (oi.purchase_price * oi.quantity))')];
        $sortColumn = $sortable[$sort] ?? $sortable['ordered_at'];

        $purchases = $base->select('oi.*', 'o.order_no', 'o.ordered_at', 'o.transaction_date', 'o.payment_status', 'o.delivery_status', 'o.order_status', 'o.buyer_name', 'sc.name as school_name', 's.student_code', DB::raw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name"), 'p.product_code as current_product_code', 'c.name as category_name')->orderBy($sortColumn, $direction)->orderByDesc('oi.id')->paginate(50)->withQueryString();
        $schools = DB::table('schools')->orderBy('id')->get(['id','name']);
        $categories = DB::table('shop_categories')->where('is_active', true)->orderBy('display_order')->get(['id','name']);
        return view('admin.account.shop-purchases.index', compact('purchases','summary','schools','categories','sort','direction'));
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->baseQuery($request)->select('oi.*','o.order_no','o.ordered_at','o.transaction_date','o.payment_status','o.delivery_status','o.order_status','o.buyer_name','sc.name as school_name','s.student_code',DB::raw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name"),'p.product_code as current_product_code','c.name as category_name')->orderByDesc('o.ordered_at')->get();
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['明細ID','注文番号','注文日時','取引日','教室','生徒コード','生徒名','購入者','商品コード','商品名','カテゴリ','数量','単価','仕入単価','割引','税額','購入金額','粗利益','付与ポイント','使用ポイント','支払状態','受渡状態','注文状態']);
            $paymentLabels = ['paid' => '入金済', 'unpaid' => '未入金', 'cancelled' => '取消済み'];
            $deliveryLabels = ['pending' => '未準備', 'preparing' => '準備中', 'ready' => '受渡可能', 'delivered' => '受渡完了', 'cancelled' => '取消済み'];
            $orderLabels = ['ordered' => '注文済', 'processing' => '処理中', 'completed' => '完了', 'cancelled' => '取消済み'];
            foreach ($rows as $r) {
                fputcsv($out,[$r->id,$r->order_no,$r->ordered_at,$r->transaction_date,$r->school_name,$r->student_code,$r->student_name,$r->buyer_name,$r->sku ?: $r->current_product_code,$r->product_name,$r->category_name,$r->quantity,$r->unit_price,$r->purchase_price,$r->discount_amount,$r->tax_amount,$r->total_amount,$r->total_amount-($r->purchase_price*$r->quantity),$r->point_reward,$r->point_used,$paymentLabels[$r->payment_status] ?? '未設定',$deliveryLabels[$r->delivery_status] ?? '未設定',$orderLabels[$r->order_status] ?? '未設定']);
            }
            fclose($out);
        },'shop-purchases-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    private function baseQuery(Request $request)
    {
        $q = DB::table('shop_order_items as oi')->join('shop_orders as o','oi.shop_order_id','=','o.id')->join('students as s','o.student_id','=','s.id')->join('schools as sc','o.school_id','=','sc.id')->leftJoin('shop_products as p','oi.shop_product_id','=','p.id')->leftJoin('shop_categories as c','p.category_id','=','c.id');
        $keyword = trim((string)$request->input('keyword'));
        if ($keyword !== '') $q->where(function($w) use($keyword){$w->where('o.order_no','like',"%{$keyword}%")->orWhere('s.student_code','like',"%{$keyword}%")->orWhere('s.last_name','like',"%{$keyword}%")->orWhere('s.first_name','like',"%{$keyword}%")->orWhere('oi.sku','like',"%{$keyword}%")->orWhere('oi.product_name','like',"%{$keyword}%");});
        foreach ([['purchased_from','o.ordered_at','>='],['transaction_from','o.transaction_date','>='],['transaction_to','o.transaction_date','<=']] as [$i,$c,$op]) {
            if ($request->filled($i)) {
                $q->where($c,$op,$request->input($i));
            }
        }
        if ($request->filled('purchased_to')) {
            $q->where('o.ordered_at', '<', \Carbon\Carbon::parse($request->input('purchased_to'))->addDay()->startOfDay());
        }
        foreach(['category_id'=>'p.category_id','school_id'=>'o.school_id','payment_status'=>'o.payment_status','delivery_status'=>'o.delivery_status','order_status'=>'o.order_status'] as $i=>$c) if($request->filled($i)) $q->where($c,$request->input($i));
        foreach(['quantity_min'=>['oi.quantity','>='],'quantity_max'=>['oi.quantity','<='],'amount_min'=>['oi.total_amount','>='],'amount_max'=>['oi.total_amount','<=']] as $i=>[$c,$op]) if($request->filled($i)) $q->where($c,$op,(int)$request->input($i));
        if(!$request->boolean('include_cancelled')) $q->where('o.order_status','!=','cancelled');
        return $q;
    }
}
