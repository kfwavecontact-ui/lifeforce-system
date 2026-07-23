@extends('layouts.admin')
@section('title','商品交換所｜商品一覧')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/reward-exchange.css') }}">
<div class="rex-page">
<header class="rex-header"><div><p>わくわく ＞ 商品交換所 ＞ 商品一覧</p><h1>商品一覧</h1><span>生徒を選択し、交換可能なポイント商品を確認・申請します。</span></div><div class="rex-header-links"><a href="{{ route('admin.wakuwaku.reward-exchange.applications') }}">申請情報</a><a href="{{ route('admin.wakuwaku.reward-exchange.history') }}">交換履歴</a></div></header>
@include('admin.wakuwaku.reward-exchange.flash')
<section class="rex-student-panel">
<div class="rex-student-main"><div class="rex-section-title"><span class="rex-section-icon">👤</span><div><b>交換する生徒</b><small>生徒を検索して選択してください</small></div></div><form method="GET" action="{{ route('admin.wakuwaku.reward-exchange.products') }}" class="rex-student-form"><label>生徒検索<input id="rex-student-search" type="search" placeholder="生徒ID・氏名で検索" autocomplete="off"></label><select name="student_id" id="rex-student-select"><option value="">生徒を選択</option>@if($student)<option selected value="{{ $student->id }}">{{ $student->student_code }}｜{{ $student->last_name }} {{ $student->first_name }}</option>@endif</select><button>この生徒で表示</button><div id="rex-student-results" class="rex-lookup-results"></div></form></div>
<div class="rex-balance-card {{ $student?'is-selected':'' }}"><span>選択中の生徒</span><strong>{{ $student ? $student->last_name.' '.$student->first_name : '未選択' }}</strong><small>{{ $student?->student_code ?? '-' }}｜{{ $student?->school?->name ?? '教室未設定' }}</small><div class="rex-balance-line"><span>現在ポイント</span><strong class="rex-points" id="rex-selected-balance">{{ number_format((int)($student?->pointBalance?->current_points ?? 0)) }}<em>pt</em></strong></div></div>
</section>
@if(!$student)
<div class="rex-guide-banner" role="status"><span>👤</span><div><b>商品を交換する生徒を選択してください</b><p>商品は閲覧できます。生徒を選択すると、ポイント残高と交換可否が反映されます。</p></div></div>
@endif
<section class="rex-filter-card"><form method="GET"><input type="hidden" name="student_id" value="{{ $student?->id }}"><input class="rex-filter-keyword" name="keyword" value="{{ request('keyword') }}" placeholder="商品コード・商品名・説明"><select name="category_id"><option value="">すべてのカテゴリ</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->name }}</option>@endforeach</select><input type="number" min="0" name="min_points" value="{{ request('min_points') }}" placeholder="最低Pt"><input type="number" min="0" name="max_points" value="{{ request('max_points') }}" placeholder="最高Pt"><div class="rex-check-group"><label><input type="checkbox" name="in_stock" value="1" @checked(request('in_stock'))> 在庫あり</label><label><input type="checkbox" name="recommended" value="1" @checked(request('recommended'))> おすすめ</label><label><input type="checkbox" name="limited" value="1" @checked(request('limited'))> 限定</label></div><button>検索</button><a href="{{ route('admin.wakuwaku.reward-exchange.products',['student_id'=>$student?->id]) }}">クリア</a></form></section>
<div class="rex-list-head"><div><b>ポイント商品</b><span>全 {{ number_format($items->total()) }} 件</span></div><small>商品カードをクリックすると詳細を確認できます。</small></div>
<section class="rex-product-grid">
@forelse($items as $item)
@php
$available=$item->is_stock_managed ? (int)($item->stock?->available_quantity ?? 0) : null;
$stock=(int)($item->stock?->stock_quantity ?? 0); $reserved=(int)($item->stock?->reserved_quantity ?? 0);
$balance=(int)($student?->pointBalance?->current_points ?? 0); $reasons=[];
if($item->publication_status!=='published')$reasons[]='非公開の商品です。';
if($item->published_at && $item->published_at->isFuture())$reasons[]='公開開始前です。';
if($item->publication_ended_at && $item->publication_ended_at->isPast())$reasons[]='公開期間が終了しています。';
if($item->is_stock_managed && $available<1)$reasons[]='在庫切れです。';
if($student && $balance<$item->required_points)$reasons[]='ポイントが不足しています。';
if(in_array($item->id,$open))$reasons[]='同じ商品の未処理申請があります。';
$stockLabel=!$item->is_stock_managed?'在庫管理なし':($available<=0?'在庫切れ':(($item->stock?->alert_quantity??0)>0 && $available<=($item->stock?->alert_quantity??0)?'残りわずか':'在庫あり'));
$stockClass=!$item->is_stock_managed?'unlimited':($available<=0?'out':(($item->stock?->alert_quantity??0)>0 && $available<=($item->stock?->alert_quantity??0)?'low':'ok'));
$image=$item->mainImage?asset('storage/'.$item->mainImage->image_path):null;
$categoryName=(string)($item->category?->name??'');
$placeholderEmoji=str_contains($categoryName,'文房')?'✏️':(str_contains($categoryName,'将棋')?'♟️':(str_contains($categoryName,'教材')||str_contains($categoryName,'知育')?'📚':(str_contains($categoryName,'カード')?'🎫':'🎁')));
$productData=json_encode(['id'=>(int)$item->id,'name'=>(string)$item->name,'code'=>(string)$item->code,'category'=>(string)($item->category?->name??'未分類'),'description'=>(string)($item->description??''),'points'=>(int)$item->required_points,'balance'=>$balance,'available'=>$available,'stock'=>$item->is_stock_managed?$stock:null,'reserved'=>$item->is_stock_managed?$reserved:null,'stockLabel'=>$stockLabel,'exchangeCount'=>(int)($item->delivered_count??0),'recommended'=>(bool)$item->is_recommended,'new'=>(bool)$item->is_new,'limited'=>(bool)$item->is_limited,'image'=>$image],JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT);
@endphp
<article class="rex-product-card" data-product="{{ $productData }}">
<div class="rex-product-image">@if($item->rank)<span class="rex-rank rank-{{ $item->rank }}">人気 {{ $item->rank }}位</span>@endif<span class="rex-stock-badge {{ $stockClass }}">{{ $stockLabel }}</span>@if($image)<img src="{{ $image }}" alt="{{ $item->name }}">@else<div class="rex-image-placeholder"><span>{{ $placeholderEmoji }}</span><b>NO IMAGE</b></div>@endif</div>
<div class="rex-product-body"><div class="rex-tags">@if($item->is_recommended)<b class="tag-recommended">★ おすすめ</b>@endif @if($item->is_new)<b class="tag-new">NEW</b>@endif @if($item->is_limited)<b class="tag-limited">限定</b>@endif</div><div class="rex-category-line"><span>{{ $item->category?->name ?? '未分類' }}</span><small>{{ $item->code }}</small></div><h2>{{ $item->name }}</h2><p title="{{ $item->description }}">{{ $item->description }}</p><div class="rex-product-meta"><strong>{{ number_format($item->required_points) }}<em>pt</em></strong><span>残り <b class="stock-{{ $stockClass }}">{{ $item->is_stock_managed?number_format(max(0,$available)):'制限なし' }}</b></span></div></div>
<footer>
@if($student && $reasons)<div class="rex-unavailable"><span>!</span>{{ implode(' ',array_unique($reasons)) }}</div>@endif
<div class="rex-card-actions">
<button type="button" class="rex-secondary rex-open-detail">詳細を見る</button>
<button type="button" class="rex-open-request" data-item="{{ $productData }}" @disabled(!$student || !empty($reasons))>{{ !$student ? '生徒選択後に申請' : (!empty($reasons) ? '交換不可' : '交換申請') }}</button>
</div>
</footer></article>
@empty<div class="rex-empty rex-empty-card"><span>🎁</span><b>該当する商品がありません</b><p>検索条件を変更してお試しください。</p><a href="{{ route('admin.wakuwaku.reward-exchange.products',['student_id'=>$student?->id]) }}">検索条件をクリア</a></div>@endforelse
</section><div class="rex-pagination">{{ $items->links() }}</div></div>
<div id="rex-toast-region" class="rex-toast-region" aria-live="polite"></div>
<div id="rex-request-modal" class="rex-modal" hidden><div class="rex-modal-card rex-request-card"><button type="button" class="rex-modal-close" aria-label="閉じる">×</button><div class="rex-modal-heading"><span>交換申請</span><h2>交換申請の確認</h2><p>以下の内容でポイント商品を申請します。</p></div><form method="POST" action="{{ route('admin.wakuwaku.reward-exchange.store') }}" id="rex-request-form">@csrf<input type="hidden" name="student_id" value="{{ $student?->id }}"><input type="hidden" name="reward_item_id" id="rex-item-id"><div class="rex-request-product"><div id="rex-modal-image" class="rex-request-thumb">🎁</div><div><span id="rex-modal-category" class="rex-category-badge"></span><h3 id="rex-item-name"></h3><strong id="rex-unit-points"></strong></div></div><div class="rex-request-summary"><article><span>生徒</span><b>{{ $student ? $student->last_name.' '.$student->first_name : '-' }}</b></article><article><span>現在ポイント</span><b>{{ number_format((int)($student?->pointBalance?->current_points ?? 0)) }} pt</b></article><article class="is-emphasis"><span>使用予定ポイント</span><b id="rex-total-points"></b></article><article><span>申請後の予定残高</span><b id="rex-after-balance"></b></article></div><div class="rex-form-block"><label>数量</label><div class="rex-stepper"><button type="button" id="rex-qty-minus">−</button><input id="rex-quantity" name="quantity" type="number" value="1" min="1" max="99" inputmode="numeric"><button type="button" id="rex-qty-plus">＋</button></div><small id="rex-quantity-help"></small></div><fieldset class="rex-delivery-options"><legend>受渡方法</legend><label><input type="radio" name="delivery_method" value="classroom" checked><span>🏫<b>教室受渡</b><small>教室で商品をお渡しします</small></span></label><label><input type="radio" name="delivery_method" value="shipping"><span>🚚<b>発送</b><small>登録先へ発送します</small></span></label></fieldset><label class="rex-note-label">備考<textarea name="note" rows="3" maxlength="1000" placeholder="受渡に関する連絡事項があれば入力してください"></textarea></label><div id="rex-request-error" class="rex-inline-error" hidden></div><div class="rex-request-notice">申請するとポイントが減算され、在庫が予約されます。</div><div class="rex-form-actions"><button type="button" class="rex-modal-close rex-cancel-button">取消</button><button type="submit" id="rex-submit-request">申請する</button></div></form></div></div>
<div id="rex-product-backdrop" class="rex-backdrop" hidden></div><aside id="rex-product-drawer" class="rex-drawer" hidden><div class="rex-drawer-head"><div><small>ポイント商品詳細</small><h2 id="rex-product-detail-title">商品詳細</h2></div><button type="button" class="rex-product-drawer-close">×</button></div><div id="rex-product-detail-content"></div></aside>
<script>window.rewardExchangeConfig={studentLookup:'{{ route('admin.wakuwaku.reward-exchange.students') }}'};</script><script src="{{ asset('js/admin/wakuwaku/reward-exchange.js') }}"></script>
@endsection
