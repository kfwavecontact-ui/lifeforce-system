@extends('layouts.admin')
@section('title','購入情報')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/shop-management.css') }}">
@php
$pay=['paid'=>'入金済','unpaid'=>'未入金','cancelled'=>'取消済み'];
$orderLabels=['ordered'=>'注文済','processing'=>'処理中','completed'=>'完了','cancelled'=>'取消済み'];
$delivery=['pending'=>'未準備','preparing'=>'準備中','ready'=>'受渡可能','delivered'=>'受渡完了','cancelled'=>'取消済み'];
$profitRate = (int)$summary->sales > 0 ? round(((int)$summary->profit/(int)$summary->sales)*100,1) : 0;
$sortLink = function(string $key) use ($sort,$direction){$next=($sort===$key&&$direction==='asc')?'desc':'asc';return request()->fullUrlWithQuery(['sort'=>$key,'direction'=>$next,'page'=>null]);};
$sortMark = fn(string $key)=>$sort===$key?($direction==='asc'?'↑':'↓'):'↕';
$hasDetails = collect(['purchased_from','purchased_to','transaction_from','transaction_to','category_id','school_id','payment_status','delivery_status','order_status','quantity_min','quantity_max','amount_min','amount_max'])->contains(fn($key)=>request()->filled($key));
@endphp
<div class="shop-mgmt-page">
<nav class="shop-breadcrumbs"><a href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}">わくわく</a><span>›</span><span>ショップ</span><span>›</span><span>購入情報</span></nav>
<header class="shop-mgmt-header">
    <div><h1>購入情報</h1><p>注文明細単位で「誰が・何を・いつ・何個購入したか」を確認します。新規登録はショップ売上画面で行います。</p></div>
    <div class="shop-mgmt-header-actions">
        <nav class="shop-screen-switcher"><a href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}">商品一覧</a><a href="{{ route('admin.operations.classroom-accounting.shop-orders.index') }}">注文情報</a><a class="is-current" href="{{ route('admin.operations.classroom-accounting.shop-purchases.index') }}">購入情報</a></nav>
        <a class="btn secondary" href="{{ route('admin.operations.classroom-accounting.shop-purchases.export',request()->query()) }}"><i class="fas fa-file-csv"></i><span>CSV出力<small class="csv-note">検索結果 {{ number_format($purchases->total()) }}件</small></span></a>
    </div>
</header>
<div class="summary-grid">
    <div class="summary-card"><span>合計購入数</span><b>{{ number_format($summary->quantity) }}個</b></div>
    <div class="summary-card"><span>合計売上</span><b>¥{{ number_format($summary->sales) }}</b></div>
    <div class="summary-card"><span>合計原価</span><b>¥{{ number_format($summary->cost) }}</b></div>
    <div class="summary-card success"><span>合計粗利益</span><b>¥{{ number_format($summary->profit) }} <small>({{ number_format($profitRate,1) }}%)</small></b></div>
</div>
<form method="get" class="filter-card">
    <div class="filter-primary">
        <label>キーワード<input name="keyword" value="{{ request('keyword') }}" placeholder="注文番号・生徒・商品コード・商品名"></label>
        <label>カテゴリ<select name="category_id"><option value="">すべて</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></label>
        <label>支払状態<select name="payment_status"><option value="">すべて</option>@foreach($pay as $k=>$v)<option value="{{ $k }}" @selected(request('payment_status')===$k)>{{ $v }}</option>@endforeach</select></label>
        <label>受渡状態<select name="delivery_status"><option value="">すべて</option>@foreach($delivery as $k=>$v)<option value="{{ $k }}" @selected(request('delivery_status')===$k)>{{ $v }}</option>@endforeach</select></label>
        <div class="filter-actions"><button class="btn primary">検索</button><a class="btn" href="{{ route('admin.operations.classroom-accounting.shop-purchases.index') }}">クリア</a></div>
    </div>
    <button type="button" class="filter-toggle" data-filter-toggle aria-expanded="{{ $hasDetails ? 'true':'false' }}">詳細条件を{{ $hasDetails ? '閉じる':'開く' }} ▼</button>
    <div class="filter-details" data-filter-details @if(!$hasDetails) hidden @endif>
        <label>購入日From<input type="date" name="purchased_from" value="{{ request('purchased_from') }}"></label><label>購入日To<input type="date" name="purchased_to" value="{{ request('purchased_to') }}"></label>
        <label>取引日From<input type="date" name="transaction_from" value="{{ request('transaction_from') }}"></label><label>取引日To<input type="date" name="transaction_to" value="{{ request('transaction_to') }}"></label>
        <label>教室<select name="school_id"><option value="">すべて</option>@foreach($schools as $s)<option value="{{ $s->id }}" @selected(request('school_id')==$s->id)>{{ $s->name }}</option>@endforeach</select></label>
        <label>注文状態<select name="order_status"><option value="">すべて</option>@foreach($orderLabels as $k=>$v)<option value="{{ $k }}" @selected(request('order_status')===$k)>{{ $v }}</option>@endforeach</select></label>
        <label>数量下限<input type="number" min="0" name="quantity_min" value="{{ request('quantity_min') }}"></label><label>数量上限<input type="number" min="0" name="quantity_max" value="{{ request('quantity_max') }}"></label>
        <label>金額下限<input type="number" min="0" name="amount_min" value="{{ request('amount_min') }}"></label><label>金額上限<input type="number" min="0" name="amount_max" value="{{ request('amount_max') }}"></label>
        <label class="check"><input type="checkbox" name="include_cancelled" value="1" @checked(request()->boolean('include_cancelled'))>取消済みを含む</label>
    </div>
</form>
<div class="table-card">
<div class="table-toolbar"><div class="table-meta">表示 {{ number_format($purchases->count()) }}件 / 全 {{ number_format($purchases->total()) }}件</div><div class="table-hint">集計・CSVとも、取消済みは初期状態で対象外です。</div></div>
<div class="table-scroll"><table><thead><tr><th class="purchase-identity-col"><a class="purchase-sort-link" href="{{ $sortLink('ordered_at') }}">注文・購入日 {{ $sortMark('ordered_at') }}</a></th><th class="action-col">操作</th><th>教室・生徒</th><th>商品</th><th>カテゴリ</th><th><a class="purchase-sort-link" href="{{ $sortLink('quantity') }}">数量 {{ $sortMark('quantity') }}</a></th><th>単価</th><th><a class="purchase-sort-link" href="{{ $sortLink('total_amount') }}">購入金額 {{ $sortMark('total_amount') }}</a></th><th>原価</th><th><a class="purchase-sort-link" href="{{ $sortLink('profit') }}">粗利益 {{ $sortMark('profit') }}</a></th><th>ポイント</th><th>支払状態</th><th>受渡状態</th><th>注文状態</th></tr></thead><tbody>
@forelse($purchases as $p)
@php $profit=(int)$p->total_amount-((int)$p->purchase_price*(int)$p->quantity); $rate=(int)$p->total_amount>0?round(($profit/(int)$p->total_amount)*100,1):0; @endphp
<tr class="{{ $p->order_status==='cancelled'?'cancelled-row':'' }}">
<td class="identity-cell purchase-identity-col"><strong>{{ $p->order_no }}</strong><small>{{ \Carbon\Carbon::parse($p->ordered_at)->format('Y/m/d H:i') }}</small></td>
<td class="action-col"><a class="btn tiny" href="{{ route('admin.operations.classroom-accounting.shop-orders.show',['order'=>$p->shop_order_id,'back'=>url()->full()]) }}">注文詳細</a></td>
<td class="identity-cell"><strong>{{ $p->student_name }}</strong><small>{{ $p->student_code }} ｜ {{ $p->school_name }}</small></td>
<td class="product-cell"><strong>{{ $p->product_name }}</strong><small>{{ $p->sku ?: $p->current_product_code ?: 'コードなし' }}</small></td>
<td>@if($p->category_name)<span class="category-chip">{{ $p->category_name }}</span>@else<span class="category-muted">未設定</span>@endif</td><td>{{ number_format($p->quantity) }}個</td><td class="amount">¥{{ number_format($p->unit_price) }}</td><td class="amount"><strong class="main-value">¥{{ number_format($p->total_amount) }}</strong></td><td class="amount">¥{{ number_format($p->purchase_price*$p->quantity) }}</td><td class="amount"><strong class="{{ $profit<0?'discount':'' }}">¥{{ number_format($profit) }}</strong><small>{{ number_format($rate,1) }}%</small></td><td><strong>付与 {{ number_format($p->point_reward) }}pt</strong><small>使用 {{ number_format($p->point_used) }}pt</small></td><td><span class="badge {{ $p->payment_status }}">{{ $pay[$p->payment_status]??'未設定' }}</span></td><td><span class="badge {{ $p->delivery_status }}">{{ $delivery[$p->delivery_status]??'未設定' }}</span></td><td><span class="badge {{ $p->order_status }}">{{ $orderLabels[$p->order_status]??'未設定' }}</span></td>
</tr>
@empty<tr><td colspan="14" class="empty-state"><i class="fas fa-shopping-bag"></i>該当する購入実績がありません。検索条件を変更してください。</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $purchases->links() }}</div></div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{const b=document.querySelector('[data-filter-toggle]'),d=document.querySelector('[data-filter-details]');if(!b||!d)return;b.addEventListener('click',()=>{const open=d.hasAttribute('hidden');if(open)d.removeAttribute('hidden');else d.setAttribute('hidden','');b.setAttribute('aria-expanded',open?'true':'false');b.textContent=`詳細条件を${open?'閉じる':'開く'} ▼`;});});</script>
@endsection
