@extends('layouts.admin')
@section('title','注文情報')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/shop-management.css') }}">
@php
$pay=['paid'=>'入金済','unpaid'=>'未入金','cancelled'=>'取消済み'];
$orderLabels=['ordered'=>'注文済','processing'=>'処理中','completed'=>'完了','cancelled'=>'取消済み'];
$delivery=['pending'=>'未準備','preparing'=>'準備中','ready'=>'受渡可能','delivered'=>'受渡完了','cancelled'=>'取消済み'];
$deliveryMethods=['classroom'=>'教室受取','shipping'=>'配送','other'=>'その他'];
$hasDetails = collect(['ordered_from','ordered_to','scheduled_from','scheduled_to','transaction_from','transaction_to','school_id','payment_method_id','payment_status','order_status','delivery_method','delivery_status','amount_min','amount_max'])->contains(fn($key)=>request()->filled($key));
@endphp
<div class="shop-mgmt-page">
<nav class="shop-breadcrumbs"><a href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}">わくわく</a><span>›</span><span>ショップ</span><span>›</span><span>注文情報</span></nav>
<header class="shop-mgmt-header">
    <div><h1>注文情報</h1><p>ショップ注文を注文単位で確認し、支払・受渡・取消を管理します。</p></div>
    <div class="shop-mgmt-header-actions">
        <nav class="shop-screen-switcher" aria-label="ショップ画面切り替え">
            <a href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}">商品一覧</a>
            <a class="is-current" href="{{ route('admin.operations.classroom-accounting.shop-orders.index') }}">注文情報</a>
            <a href="{{ route('admin.operations.classroom-accounting.shop-purchases.index') }}">購入情報</a>
        </nav>
        <a class="btn secondary" href="{{ route('admin.operations.classroom-accounting.shop-orders.export',request()->query()) }}"><i class="fas fa-file-csv"></i><span>CSV出力<small class="csv-note">検索結果 {{ number_format($summary['count']) }}件</small></span></a>
    </div>
</header>
@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
<div class="summary-grid">
    <div class="summary-card"><span>検索結果</span><b>{{ number_format($summary['count']) }}件</b></div>
    <div class="summary-card"><span>請求金額</span><b>¥{{ number_format($summary['amount']) }}</b></div>
    <a class="summary-card warning is-clickable" href="{{ request()->fullUrlWithQuery(['unpaid_only'=>1,'page'=>null]) }}"><span>未入金</span><b>{{ number_format($summary['unpaid']) }}件</b></a>
    <a class="summary-card danger is-clickable" href="{{ request()->fullUrlWithQuery(['undelivered_only'=>1,'page'=>null]) }}"><span>未受渡</span><b>{{ number_format($summary['undelivered']) }}件</b></a>
</div>
<form method="get" class="filter-card" id="shopOrderFilter">
    <div class="filter-primary">
        <label>キーワード<input name="keyword" value="{{ request('keyword') }}" placeholder="注文番号・生徒・購入者・商品名"></label>
        <label>支払状態<select name="payment_status"><option value="">すべて</option>@foreach($pay as $k=>$v)<option value="{{ $k }}" @selected(request('payment_status')===$k)>{{ $v }}</option>@endforeach</select></label>
        <label>注文状態<select name="order_status"><option value="">すべて</option>@foreach($orderLabels as $k=>$v)<option value="{{ $k }}" @selected(request('order_status')===$k)>{{ $v }}</option>@endforeach</select></label>
        <label>受渡状態<select name="delivery_status"><option value="">すべて</option>@foreach($delivery as $k=>$v)<option value="{{ $k }}" @selected(request('delivery_status')===$k)>{{ $v }}</option>@endforeach</select></label>
        <div class="filter-actions"><button class="btn primary">検索</button><a class="btn" href="{{ route('admin.operations.classroom-accounting.shop-orders.index') }}">クリア</a></div>
    </div>
    <button type="button" class="filter-toggle" data-filter-toggle aria-expanded="{{ $hasDetails ? 'true':'false' }}">詳細条件を{{ $hasDetails ? '閉じる':'開く' }} ▼</button>
    <div class="filter-details" data-filter-details @if(!$hasDetails) hidden @endif>
        <label>注文日From<input type="date" name="ordered_from" value="{{ request('ordered_from') }}"></label><label>注文日To<input type="date" name="ordered_to" value="{{ request('ordered_to') }}"></label>
        <label>予定日From<input type="date" name="scheduled_from" value="{{ request('scheduled_from') }}"></label><label>予定日To<input type="date" name="scheduled_to" value="{{ request('scheduled_to') }}"></label>
        <label>取引日From<input type="date" name="transaction_from" value="{{ request('transaction_from') }}"></label><label>取引日To<input type="date" name="transaction_to" value="{{ request('transaction_to') }}"></label>
        <label>教室<select name="school_id"><option value="">すべて</option>@foreach($schools as $s)<option value="{{ $s->id }}" @selected(request('school_id')==$s->id)>{{ $s->name }}</option>@endforeach</select></label>
        <label>支払方法<select name="payment_method_id"><option value="">すべて</option>@foreach($paymentMethods as $m)<option value="{{ $m->id }}" @selected(request('payment_method_id')==$m->id)>{{ $m->name }}</option>@endforeach</select></label>
        <label>受渡方法<select name="delivery_method"><option value="">すべて</option>@foreach($deliveryMethods as $k=>$v)<option value="{{ $k }}" @selected(request('delivery_method')===$k)>{{ $v }}</option>@endforeach</select></label>
        <label>金額下限<input type="number" min="0" name="amount_min" value="{{ request('amount_min') }}"></label><label>金額上限<input type="number" min="0" name="amount_max" value="{{ request('amount_max') }}"></label>
        <label class="check"><input type="checkbox" name="unpaid_only" value="1" @checked(request()->boolean('unpaid_only'))>未入金のみ</label><label class="check"><input type="checkbox" name="undelivered_only" value="1" @checked(request()->boolean('undelivered_only'))>未受渡のみ</label>
    </div>
</form>
<div class="table-card">
    <div class="table-toolbar"><div class="table-meta">表示 {{ number_format($orders->count()) }}件 / 全 {{ number_format($orders->total()) }}件</div><div class="table-hint">注文番号・生徒・商品・請求額・各状態を中心に表示しています。</div></div>
    <div class="table-scroll"><table><thead><tr><th class="order-identity-col">注文</th><th class="action-col">操作</th><th>注文日時</th><th>教室・生徒</th><th>購入者</th><th>商品概要</th><th>点数・数量</th><th>請求金額</th><th>支払方法</th><th>支払状態</th><th>注文状態</th><th>受渡状態</th><th>予定日・取引日</th></tr></thead><tbody>
    @forelse($orders as $o)
    <tr class="{{ $o->order_status==='cancelled'?'cancelled-row':'' }}">
        <td class="identity-cell order-identity-col"><strong>{{ $o->order_no }}</strong><small>ID {{ $o->id }}</small></td>
        <td class="action-col"><a class="btn tiny" href="{{ route('admin.operations.classroom-accounting.shop-orders.show',['order'=>$o->id,'back'=>url()->full()]) }}">詳細</a></td>
        <td>{{ optional(\Carbon\Carbon::parse($o->ordered_at))->format('Y/m/d') }}<small>{{ optional(\Carbon\Carbon::parse($o->ordered_at))->format('H:i') }}</small></td>
        <td class="identity-cell"><strong>{{ $o->student_name }}</strong><small>{{ $o->student_code }} ｜ {{ $o->school_name }}</small></td>
        <td>{{ trim((string)$o->buyer_name) === trim((string)$o->student_name) ? '生徒本人' : ($o->buyer_name ?: '-') }}</td>
        <td class="product-cell" title="{{ $o->item_summary }}">@php($itemNames=collect(explode('、',(string)$o->item_summary))->filter()->values())<strong>{{ $itemNames->first() ?: '-' }}@if($itemNames->count()>1) <span class="muted">ほか{{ $itemNames->count()-1 }}商品</span>@endif</strong><small>合計 {{ $o->item_count }}商品</small></td>
        <td><div class="compact-metrics"><span><b>{{ $o->item_count }}</b><small>商品</small></span><span><b>{{ $o->total_quantity }}</b><small>個</small></span></div></td>
        <td class="amount"><strong class="main-value">¥{{ number_format($o->total_amount) }}</strong>@if($o->discount_amount>0)<small class="discount">割引 ¥{{ number_format($o->discount_amount) }}</small>@else<small>割引なし</small>@endif</td>
        <td>{{ $o->payment_method_name ?: '未設定' }}</td>
        <td><span class="badge {{ $o->payment_status }}">{{ $pay[$o->payment_status]??'未設定' }}</span></td>
        <td><span class="badge {{ $o->order_status }}">{{ $orderLabels[$o->order_status]??'未設定' }}</span></td>
        <td><div class="status-stack"><span class="badge {{ $o->delivery_status }}">{{ $delivery[$o->delivery_status]??'未設定' }}</span><small>{{ $deliveryMethods[$o->delivery_method]??'未設定' }}</small></div></td>
        <td>{{ $o->scheduled_date ? \Carbon\Carbon::parse($o->scheduled_date)->format('Y/m/d') : '-' }}<small>取引 {{ $o->transaction_date ? \Carbon\Carbon::parse($o->transaction_date)->format('Y/m/d') : '-' }}</small></td>
    </tr>
    @empty<tr><td colspan="13" class="empty-state"><i class="fas fa-receipt"></i>該当する注文がありません。検索条件を変更してください。</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $orders->links() }}</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const b=document.querySelector('[data-filter-toggle]'),d=document.querySelector('[data-filter-details]');if(!b||!d)return;b.addEventListener('click',()=>{const open=d.hasAttribute('hidden');if(open)d.removeAttribute('hidden');else d.setAttribute('hidden','');b.setAttribute('aria-expanded',open?'true':'false');b.textContent=`詳細条件を${open?'閉じる':'開く'} ▼`;});});
</script>
@endsection
