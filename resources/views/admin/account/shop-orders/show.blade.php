@extends('layouts.admin')
@section('title','注文詳細')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/shop-management.css') }}">
@php
$back=request('back',route('admin.operations.classroom-accounting.shop-orders.index'));
$cancelled=$record->order_status==='cancelled';
$pay=['paid'=>'入金済','unpaid'=>'未入金','cancelled'=>'取消済み'];
$orderLabels=['ordered'=>'注文済','processing'=>'処理中','completed'=>'完了','cancelled'=>'取消済み'];
$delivery=['pending'=>'未準備','preparing'=>'準備中','ready'=>'受渡可能','delivered'=>'受渡完了','cancelled'=>'取消済み'];
$deliveryMethods=['classroom'=>'教室受取','shipping'=>'配送','other'=>'その他'];
$accountLabels=['planned'=>'予定','confirmed'=>'確定','cancelled'=>'取消済み'];
@endphp
<div class="shop-mgmt-page">
<nav class="shop-breadcrumbs"><a href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}">わくわく</a><span>›</span><span>ショップ</span><span>›</span><a href="{{ route('admin.operations.classroom-accounting.shop-orders.index') }}">注文情報</a><span>›</span><span>注文詳細</span></nav>
<header class="shop-mgmt-header">
    <div><h1>注文詳細</h1><p>{{ $record->order_no }} の注文・支払・受渡・会計連携を確認します。</p></div>
    <div class="shop-mgmt-header-actions"><a class="btn" href="{{ $back }}">一覧へ戻る</a></div>
</header>
@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>入力内容を確認してください。</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="detail-status-strip">
    <div class="detail-status-item"><span>支払状態</span><span class="badge {{ $record->payment_status }}">{{ $pay[$record->payment_status]??'未設定' }}</span></div>
    <div class="detail-status-item"><span>注文状態</span><span class="badge {{ $record->order_status }}">{{ $orderLabels[$record->order_status]??'未設定' }}</span></div>
    <div class="detail-status-item"><span>受渡状態</span><span class="badge {{ $record->delivery_status }}">{{ $delivery[$record->delivery_status]??'未設定' }}</span></div>
</div>

<div class="detail-grid">
<section class="detail-card"><h2>注文基本情報</h2><dl>
<dt>注文番号</dt><dd><strong>{{ $record->order_no }}</strong><button type="button" class="copy-order-button" data-copy-order="{{ $record->order_no }}" title="注文番号をコピー"><i class="fas fa-copy"></i></button></dd>
<dt>注文日時</dt><dd>{{ \Carbon\Carbon::parse($record->ordered_at)->format('Y/m/d H:i') }}</dd>
<dt>教室</dt><dd>{{ $record->school_name }}</dd>
<dt>生徒</dt><dd>{{ $record->student_name }}（{{ $record->student_code }}）</dd>
<dt>購入者</dt><dd>{{ $record->buyer_name ?: '-' }}@if($record->buyer_email)<br>{{ $record->buyer_email }}@endif @if($record->buyer_phone)<br>{{ $record->buyer_phone }}@endif</dd>
<dt>メモ</dt><dd>{{ $record->memo ?: '-' }}</dd>
@if($cancelled)<dt>取消日時</dt><dd>{{ $record->cancelled_at ? \Carbon\Carbon::parse($record->cancelled_at)->format('Y/m/d H:i') : '-' }}</dd><dt>取消理由</dt><dd class="discount">{{ $record->cancel_reason ?: '理由未登録' }}</dd>@endif
</dl></section>
<section class="detail-card"><h2>金額内訳</h2><dl>
<dt>割引前金額</dt><dd class="amount">¥{{ number_format($record->subtotal_amount) }}</dd>
<dt>通常割引</dt><dd class="amount {{ $record->discount_amount > 0 ? 'discount' : 'amount-zero' }}">{{ $record->discount_amount > 0 ? '-¥'.number_format($record->discount_amount) : '¥0' }}</dd>
<dt>クーポン割引</dt><dd class="amount {{ $record->coupon_discount > 0 ? 'discount' : 'amount-zero' }}">{{ $record->coupon_discount > 0 ? '-¥'.number_format($record->coupon_discount) : '¥0' }}</dd>
<dt>ポイント割引</dt><dd class="amount {{ $record->point_discount > 0 ? 'discount' : 'amount-zero' }}">{{ $record->point_discount > 0 ? '-¥'.number_format($record->point_discount) : '¥0' }}</dd>
<dt>キャンペーン割引</dt><dd class="amount {{ $record->campaign_discount > 0 ? 'discount' : 'amount-zero' }}">{{ $record->campaign_discount > 0 ? '-¥'.number_format($record->campaign_discount) : '¥0' }}</dd>
<dt>送料</dt><dd class="amount">¥{{ number_format($record->shipping_fee) }}</dd>
<dt>税額</dt><dd class="amount">¥{{ number_format($record->tax_amount) }}</dd>
<dt>最終金額</dt><dd class="amount grand-total">¥{{ number_format($record->total_amount) }} <small>税込</small></dd>
</dl></section>
</div>

<section class="table-card" style="margin-bottom:16px"><div class="table-toolbar"><h2 style="margin:0;font-size:17px">注文明細</h2><div class="table-meta">{{ number_format($items->count()) }}件</div></div><div class="table-scroll"><table style="min-width:1100px"><thead><tr><th>商品</th><th>数量</th><th>単価</th><th>仕入価格</th><th>割引</th><th>税率</th><th>税額</th><th>小計</th><th>合計</th><th>付与pt</th><th>使用pt</th></tr></thead><tbody>@forelse($items as $i)<tr><td class="product-cell"><strong>{{ $i->product_name }}</strong><small>{{ $i->sku ?: $i->current_product_code }}</small></td><td>{{ number_format($i->quantity) }}個</td><td class="amount">¥{{ number_format($i->unit_price) }}</td><td class="amount">¥{{ number_format($i->purchase_price) }}</td><td class="amount {{ $i->discount_amount > 0 ? 'discount' : 'amount-zero' }}">{{ $i->discount_amount > 0 ? '-¥'.number_format($i->discount_amount) : '¥0' }}</td><td>{{ number_format((float)$i->tax_rate, ((float)$i->tax_rate == floor((float)$i->tax_rate)) ? 0 : 1) }}%</td><td class="amount">¥{{ number_format($i->tax_amount) }}</td><td class="amount">¥{{ number_format($i->subtotal_amount) }}</td><td class="amount"><strong>¥{{ number_format($i->total_amount) }}</strong></td><td>{{ number_format($i->point_reward) }}pt</td><td>{{ number_format($i->point_used) }}pt</td></tr>@empty<tr><td colspan="11" class="empty-state">注文明細がありません。</td></tr>@endforelse</tbody></table></div></section>

<div class="detail-grid">
<section class="detail-card"><h2>支払・受渡情報</h2>
@if(!$cancelled)
<form method="post" action="{{ route('admin.operations.classroom-accounting.shop-orders.update',$record->id) }}" class="edit-form" data-dirty-form>@csrf @method('put')<div class="section-title">支払情報</div>
<label>支払方法<select name="payment_method_id"><option value="">未設定</option>@foreach($paymentMethods as $m)<option value="{{ $m->id }}" @selected($record->payment_method_id==$m->id)>{{ $m->name }}</option>@endforeach</select></label>
<label>支払状態<select name="payment_status"><option value="unpaid" @selected($record->payment_status==='unpaid')>未入金</option><option value="paid" @selected($record->payment_status==='paid')>入金済</option></select></label>
<div class="section-title">注文・受渡情報</div><label>注文状態<select name="order_status"><option value="ordered" @selected($record->order_status==='ordered')>注文済</option><option value="processing" @selected($record->order_status==='processing')>処理中</option><option value="completed" @selected($record->order_status==='completed')>完了</option></select></label>
<label>受渡方法<select name="delivery_method">@foreach($deliveryMethods as $k=>$v)<option value="{{ $k }}" @selected($record->delivery_method===$k)>{{ $v }}</option>@endforeach</select></label>
<label>受渡状態<select name="delivery_status">@foreach(['pending'=>'未準備','preparing'=>'準備中','ready'=>'受渡可能','delivered'=>'受渡完了'] as $k=>$v)<option value="{{ $k }}" @selected($record->delivery_status===$k)>{{ $v }}</option>@endforeach</select></label>
<label>取引予定日<input type="date" name="scheduled_date" value="{{ $record->scheduled_date }}"></label>
<label>取引日<input type="date" name="transaction_date" value="{{ $record->transaction_date }}"></label>
<label class="full">メモ<textarea name="memo">{{ $record->memo }}</textarea></label>
<div class="full form-footer"><span class="unsaved-note">未保存の変更があります</span><button class="btn primary loading-button" type="submit">変更を保存</button></div>
</form>
<form method="post" action="{{ route('admin.operations.classroom-accounting.shop-orders.cancel',$record->id) }}" class="cancel-form" data-cancel-form>@csrf<label>取消理由<textarea name="cancel_reason" required minlength="2" maxlength="1000" placeholder="取消理由を入力してください"></textarea><small class="cancel-help">取消後は注文・支払・受渡・会計状態が取消済みになります。現在の販売処理は在庫減算を行っていないため、在庫は変更しません。</small></label><div class="cancellation-impact"><strong>取消時の更新内容</strong><br>注文状態・支払状態・受渡状態・会計状態を取消済みに統一します。二重取消はできません。</div><div class="form-footer"><button class="btn danger loading-button" type="submit">注文を取り消す</button></div></form>
@else
<div class="cancel-note">この注文は取消済みです。支払・受渡情報は変更できません。</div>
@endif
</section>
<section class="detail-card"><h2>会計・受渡連携</h2><dl>
<dt>会計連携</dt><dd>{{ $accountTransaction ? '連携済み' : '未連携' }}</dd>
<dt>会計取引ID</dt><dd>{{ $accountTransaction->id ?? '-' }}</dd>
<dt>会計状態</dt><dd>@if($accountTransaction)<span class="badge {{ $accountTransaction->status }}">{{ $accountLabels[$accountTransaction->status] ?? '未設定' }}</span>@else-@endif</dd>
<dt>会計金額</dt><dd>{{ $accountTransaction ? '¥'.number_format($accountTransaction->amount) : '-' }}</dd>
<dt>受渡担当</dt><dd>{{ $pickup->handled_by_name ?? '-' }}</dd>
<dt>受渡完了日時</dt><dd>{{ $pickup?->picked_up_at ? \Carbon\Carbon::parse($pickup->picked_up_at)->format('Y/m/d H:i') : '-' }}</dd>
</dl>
<details class="technical-details"><summary>システム連携情報を表示</summary><dl><dt>元テーブル / ID</dt><dd>{{ $accountTransaction ? $accountTransaction->source_table.' / '.$accountTransaction->source_id : '-' }}</dd><dt>受渡レコード</dt><dd>{{ $pickup?->id ?? '-' }}</dd></dl></details>
</section>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-copy-order]').forEach(button=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copyOrder);button.innerHTML='<i class="fas fa-check"></i>';setTimeout(()=>button.innerHTML='<i class="fas fa-copy"></i>',1200)}catch(e){window.prompt('注文番号をコピーしてください',button.dataset.copyOrder)}}));document.querySelector('[data-cancel-form]')?.addEventListener('submit',event=>{const reason=event.currentTarget.querySelector('[name="cancel_reason"]');if(!reason.value.trim()){event.preventDefault();reason.focus();return}if(!confirm('この注文を取り消します。注文・支払・受渡・会計状態が取消済みになります。よろしいですか？'))event.preventDefault()});document.querySelectorAll('[data-dirty-form]').forEach(form=>{const note=form.querySelector('.unsaved-note');let dirty=false;form.addEventListener('input',()=>{dirty=true;note?.classList.add('is-visible')});form.addEventListener('submit',()=>{dirty=false;const b=form.querySelector('button[type="submit"]');if(b){b.disabled=true;b.textContent='保存中…'}});window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue=''}})});document.querySelectorAll('.loading-button').forEach(b=>b.closest('form')?.addEventListener('submit',()=>{b.disabled=true}));});
</script>
@endsection
