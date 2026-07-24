@extends('layouts.admin')

@section('title', '商品一覧')

@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/shop-products.css') }}">

@php
    $sortLink = function (string $key) use ($sort, $direction) {
        $next = ($sort === $key && $direction === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $next, 'page' => null]);
    };
    $sortMark = fn (string $key) => $sort === $key ? ($direction === 'asc' ? '↑' : '↓') : '↕';
@endphp

<div class="shop-products-page" data-update-base="{{ url('/admin/operations/classroom-accounting/shop-products') }}">
    <nav class="shop-breadcrumbs" aria-label="パンくず">
        <a href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}">わくわく</a><span>›</span><span>ショップ</span><span>›</span><span>商品一覧</span>
    </nav>

    <header class="shop-products-header">
        <div>
            <h1>商品一覧</h1>
            <p>ショップで販売する商品の基本情報・価格・在庫・公開状態を管理します。</p>
        </div>
        <div class="shop-products-header-actions">
            <nav class="shop-screen-switcher" aria-label="ショップ画面切り替え">
                <a class="is-current" href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}">商品一覧</a>
                <a href="{{ route('admin.operations.classroom-accounting.shop-orders.index') }}">注文情報</a>
                <a href="{{ route('admin.operations.classroom-accounting.shop-purchases.index') }}">購入情報</a>
            </nav>
            <button type="button" class="shop-products-primary-button" data-open-product-modal="create">
                <i class="fas fa-plus"></i> 商品を追加
            </button>
        </div>
    </header>

    @if (session('success'))
        <div class="shop-products-alert success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="shop-products-alert error" role="alert">
            <strong>入力内容を確認してください。</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="shop-products-summary-grid" aria-label="商品集計">
        <div class="shop-products-summary-card"><span>登録商品数</span><strong>{{ number_format($summary['total']) }}</strong></div>
        <div class="shop-products-summary-card"><span>販売中</span><strong>{{ number_format($summary['active']) }}</strong></div>
        <div class="shop-products-summary-card"><span>販売停止</span><strong>{{ number_format($summary['inactive']) }}</strong></div>
        <div class="shop-products-summary-card warning"><span>在庫警告</span><strong>{{ number_format($summary['stock_alert']) }}</strong></div>
    </section>

    <section class="shop-products-filter-card">
        <form method="GET" action="{{ route('admin.operations.classroom-accounting.shop-products.index') }}" class="shop-products-filter-form">
            <label>キーワード
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="商品コード・商品名・説明・バーコード">
            </label>
            <label>商品カテゴリ
                <select name="category_id"><option value="">すべて</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select>
            </label>
            <label>教室
                <select name="school_id"><option value="">すべて</option>@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(($filters['school_id'] ?? '') == $school->id)>{{ $school->name }}</option>@endforeach</select>
            </label>
            <label>有効状態
                <select name="active_status"><option value="">すべて</option><option value="1" @selected(($filters['active_status'] ?? '') === '1')>有効</option><option value="0" @selected(($filters['active_status'] ?? '') === '0')>無効</option></select>
            </label>
            <label>オンライン
                <select name="online_status"><option value="">すべて</option><option value="1" @selected(($filters['online_status'] ?? '') === '1')>オンライン公開</option><option value="0" @selected(($filters['online_status'] ?? '') === '0')>店頭のみ</option></select>
            </label>
            <label>在庫状態
                <select name="stock_status"><option value="">すべて</option><option value="available" @selected(($filters['stock_status'] ?? '') === 'available')>在庫あり</option><option value="low" @selected(($filters['stock_status'] ?? '') === 'low')>残りわずか</option><option value="out" @selected(($filters['stock_status'] ?? '') === 'out')>在庫切れ</option></select>
            </label>
            <div class="shop-products-filter-actions">
                <button type="submit" class="shop-products-search-button">検索</button>
                <a href="{{ route('admin.operations.classroom-accounting.shop-products.index') }}" class="shop-products-clear-button">クリア</a>
            </div>
        </form>
    </section>

    <section class="shop-products-table-card">
        <div class="shop-products-table-meta"><span>表示中 {{ number_format($products->count()) }}件 / 全 {{ number_format($products->total()) }}件</span><span class="table-quality-hint">商品名・価格・在庫・公開状態を一覧で確認できます。</span></div>
        <div class="shop-products-table-scroll">
            <table class="shop-products-table">
                <thead><tr>
                    <th class="order-column"><a href="{{ $sortLink('display_order') }}">表示順 {{ $sortMark('display_order') }}</a></th>
                    <th><a href="{{ $sortLink('id') }}">ID {{ $sortMark('id') }}</a></th>
                    <th>画像</th>
                    <th class="product-main-col"><a href="{{ $sortLink('product_code') }}">商品 {{ $sortMark('product_code') }}</a></th>
                    <th>カテゴリ</th><th>教室</th>
                    <th><a href="{{ $sortLink('price') }}">販売価格 {{ $sortMark('price') }}</a></th>
                    <th><a href="{{ $sortLink('purchase_price') }}">仕入価格 {{ $sortMark('purchase_price') }}</a></th>
                    <th>粗利益</th>
                    <th><a href="{{ $sortLink('stock_quantity') }}">在庫 {{ $sortMark('stock_quantity') }}</a></th>
                    <th>ポイント</th><th>販売区分</th><th>公開状態</th><th>有効</th>
                    <th><a href="{{ $sortLink('updated_at') }}">更新日 {{ $sortMark('updated_at') }}</a></th><th>操作</th>
                </tr></thead>
                <tbody>
                @forelse ($products as $product)
                    @php
                        $alertQuantity = (int) ($product->alert_quantity ?? 5);
                        $stockClass = !$product->is_stock_managed ? 'unmanaged' : ($product->stock_quantity <= 0 ? 'out' : ($product->stock_quantity <= $alertQuantity ? 'low' : 'available'));
                        $stockText = !$product->is_stock_managed ? '管理なし' : number_format($product->stock_quantity) . '個';
                        $imageUrl = $product->image_path ? asset('storage/' . ltrim($product->image_path, '/')) : null;
                        $profit = (int) $product->price - (int) $product->purchase_price;
                        $profitRate = (int) $product->price > 0 ? round(($profit / (int) $product->price) * 100, 1) : 0;
                        $now = now();
                        $publicationClass = 'active';
                        $publicationText = '公開中';
                        if ($product->published_at && $product->published_at->isFuture()) { $publicationClass = 'scheduled'; $publicationText = '公開前'; }
                        if ($product->sales_end_at && $product->sales_end_at->isPast()) { $publicationClass = 'ended'; $publicationText = '終了済'; }
                    @endphp
                    <tr>
                        <td class="order-column"><span class="order-value" title="小さい数字ほど上に表示されます">{{ $product->display_order }}</span></td><td>{{ $product->id }}</td>
                        <td><div class="shop-product-thumb">@if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $product->name }}">@else<i class="fas fa-image"></i>@endif</div></td>
                        <td class="shop-product-name-cell product-main-col"><strong>{{ $product->name }}</strong><span class="product-code-line">{{ $product->product_code }}</span><span class="product-description-line">{{ \Illuminate\Support\Str::limit($product->description ?: '説明なし', 42) }}</span></td>
                        <td><span class="category-chip">{{ $product->category?->name ?? '未設定' }}</span></td><td>{{ $product->school?->name ?? '-' }}</td>
                        <td class="amount"><strong>¥{{ number_format($product->price) }}</strong><small>税込</small></td>
                        <td class="amount">¥{{ number_format($product->purchase_price) }}</td>
                        <td class="amount profit-cell"><strong class="{{ $profit < 0 ? 'discount' : '' }}">¥{{ number_format($profit) }}</strong><small>粗利率 {{ number_format($profitRate,1) }}%</small></td>
                        <td><div class="stock-detail"><span class="stock-badge {{ $stockClass }}">{{ $stockText }}</span>@if($product->is_stock_managed)<small>警告 {{ number_format($alertQuantity) }}個以下</small>@endif</div></td>
                        <td class="point-cell"><strong>付与 {{ number_format($product->point_reward) }}pt</strong><small>購入 {{ number_format($product->point_price) }}pt</small></td>
                        <td><span class="status-badge {{ $product->is_online ? 'active' : 'inactive' }}">{{ $product->is_online ? 'オンライン公開' : '店頭のみ' }}</span></td>
                        <td class="period-cell"><span class="publication-badge {{ $publicationClass }}">{{ $publicationText }}</span><small>{{ $product->published_at?->format('Y/m/d') ?? '開始指定なし' }} ～ {{ $product->sales_end_at?->format('Y/m/d') ?? '終了なし' }}</small></td>
                        <td><span class="status-badge {{ $product->is_active ? 'active' : 'inactive' }}">{{ $product->is_active ? '有効' : '無効' }}</span></td>
                        <td>{{ $product->updated_at?->format('Y/m/d') }}<small>{{ $product->updated_at?->format('H:i') }}</small></td>
                        @php
                            $productPayload = base64_encode(json_encode([
                                'id'=>$product->id,'school_id'=>$product->school_id,'category_id'=>$product->category_id,
                                'product_code'=>$product->product_code,'name'=>$product->name,'description'=>$product->description,
                                'price'=>$product->price,'purchase_price'=>$product->purchase_price,'tax_rate'=>$product->tax_rate,
                                'stock_quantity'=>$product->stock_quantity,'alert_quantity'=>$alertQuantity,'is_stock_managed'=>$product->is_stock_managed,
                                'point_reward'=>$product->point_reward,'point_price'=>$product->point_price,'barcode'=>$product->barcode,
                                'is_online'=>$product->is_online,'published_at'=>$product->published_at?->format('Y-m-d\TH:i'),
                                'sales_end_at'=>$product->sales_end_at?->format('Y-m-d\TH:i'),'display_order'=>$product->display_order,
                                'is_active'=>$product->is_active,'image_url'=>$imageUrl,
                            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                        @endphp
                        <td class="product-action-col"><button type="button" class="shop-products-edit-button" data-open-product-modal="edit" data-product="{{ $productPayload }}">編集</button></td>
                    </tr>
                @empty
                    <tr><td colspan="16" class="shop-products-empty"><i class="fas fa-box-open"></i><br>該当する商品がありません。検索条件を変更するか、商品を追加してください。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="shop-products-pagination">{{ $products->links() }}</div>
    </section>
</div>

<div class="shop-products-modal" id="shopProductModal" aria-hidden="true">
    <div class="shop-products-modal-panel" role="dialog" aria-modal="true" aria-labelledby="shopProductModalTitle">
        <div class="shop-products-modal-header">
            <div><h2 id="shopProductModalTitle">商品を追加</h2><p id="shopProductModalSubtitle">商品情報を区分ごとに入力してください。</p></div>
            <button type="button" data-close-product-modal aria-label="閉じる">×</button>
        </div>
        <div class="shop-products-modal-tabs" role="tablist">
            <button type="button" class="is-active" data-product-tab="basic">基本情報 <span class="tab-state" data-tab-state="basic"></span></button>
            <button type="button" data-product-tab="price">価格・ポイント <span class="tab-state" data-tab-state="price"></span></button>
            <button type="button" data-product-tab="stock">在庫・公開 <span class="tab-state" data-tab-state="stock"></span></button>
            <button type="button" data-product-tab="image">商品画像 <span class="tab-state" data-tab-state="image"></span></button>
        </div>
        <form method="POST" action="{{ route('admin.operations.classroom-accounting.shop-products.store') }}" enctype="multipart/form-data" id="shopProductForm" novalidate>
            @csrf
            <input type="hidden" name="_method" id="shopProductMethod" value="POST"><input type="hidden" name="editing_product_id" id="shopProductEditingId" value="">
            <div class="shop-products-modal-body">
                <section class="product-tab-panel is-active" data-product-panel="basic">
                    <h3>基本情報</h3>
                    <div class="shop-products-form-grid">
                        <label>商品コード <span>必須</span><input type="text" name="product_code" required maxlength="50" placeholder="例：PRD0011"><small class="field-help">重複しない管理用コードを入力します。</small><small class="field-error">商品コードを入力してください。</small></label>
                        <label>商品名 <span>必須</span><input type="text" name="name" required maxlength="255" placeholder="商品名"><small class="field-error">商品名を入力してください。</small></label>
                        <label>商品カテゴリ <span>必須</span><select name="category_id" required><option value="">選択してください</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><small class="field-error">カテゴリを選択してください。</small></label>
                        <label>教室 <span>必須</span><select name="school_id" required><option value="">選択してください</option>@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select><small class="field-error">教室を選択してください。</small></label>
                        <label>バーコード<input type="text" name="barcode" maxlength="100" placeholder="JANコード等"></label>
                        <label>表示順 <span>必須</span><input type="number" name="display_order" min="0" max="999999" required placeholder="0"><small class="field-help">小さい数字ほど一覧の上に表示されます。</small></label>
                        <label class="full">説明<textarea name="description" rows="4" maxlength="2000" placeholder="商品の内容や利用方法を入力します。"></textarea><small class="field-help"><span id="shopProductDescriptionCount">0</span> / 2000文字</small></label>
                    </div>
                </section>

                <section class="product-tab-panel" data-product-panel="price">
                    <h3>価格・ポイント</h3>
                    <div class="shop-products-form-grid four">
                        <label>販売価格 <span>必須</span><span class="input-with-unit"><input type="number" name="price" min="0" max="999999999" required placeholder="0"><b>円</b></span></label>
                        <label>仕入価格 <span>必須</span><span class="input-with-unit"><input type="number" name="purchase_price" min="0" max="999999999" required placeholder="0"><b>円</b></span></label>
                        <label>税率（%） <span>必須</span><select name="tax_rate" required><option value="0">0%</option><option value="8">8%</option><option value="10">10%</option></select></label>
                        <label>付与ポイント <span>必須</span><span class="input-with-unit"><input type="number" name="point_reward" min="0" max="999999999" required placeholder="0"><b>pt</b></span></label>
                        <label>ポイント購入価格 <span>必須</span><span class="input-with-unit"><input type="number" name="point_price" min="0" max="999999999" required placeholder="0"><b>pt</b></span></label>
                    </div>
                    <div class="price-preview">
                        <div><span>粗利益</span><strong id="shopProductProfit">¥0</strong></div>
                        <div><span>粗利率</span><strong id="shopProductProfitRate">0.0%</strong></div>
                    </div>
                </section>

                <section class="product-tab-panel" data-product-panel="stock">
                    <h3>在庫・公開設定</h3><div class="publication-preview" id="shopProductPublicationPreview">公開期間を設定すると、現在の公開状態をここに表示します。</div>
                    <div class="shop-products-form-grid four">
                        <label>現在庫数 <span>必須</span><input type="number" name="stock_quantity" min="0" max="9999999" required placeholder="0"></label>
                        <label>在庫警告数 <span>必須</span><input type="number" name="alert_quantity" min="0" max="9999999" required placeholder="5"></label>
                        <label>公開開始日時<input type="datetime-local" name="published_at"></label>
                        <label>販売終了日時<input type="datetime-local" name="sales_end_at"><small class="field-error">終了日時は開始日時以降にしてください。</small></label>
                    </div>
                    <div class="switch-list" style="margin-top:16px">
                        <label class="switch-row"><span><strong>在庫を管理する</strong><small>販売可能数と在庫警告を管理します。</small></span><input type="checkbox" name="is_stock_managed" value="1" checked></label>
                        <label class="switch-row"><span><strong>オンライン販売する</strong><small>オンラインショップへ公開対象とします。</small></span><input type="checkbox" name="is_online" value="1"></label>
                        <label class="switch-row"><span><strong>有効にする</strong><small>無効にすると新規販売の選択対象外になります。</small></span><input type="checkbox" name="is_active" value="1" checked></label>
                    </div>
                </section>

                <section class="product-tab-panel" data-product-panel="image">
                    <h3>商品画像</h3>
                    <div class="shop-products-image-field">
                        <div><div class="image-caption" id="shopProductImageCaption">商品画像プレビュー</div><div class="shop-products-image-preview" id="shopProductImagePreview"><i class="fas fa-image"></i><span>画像未選択</span></div></div>
                        <div class="image-upload-panel">
                            <input class="visually-hidden-file" type="file" name="image" id="shopProductImage" accept="image/jpeg,image/png,image/webp">
                            <label for="shopProductImage" class="custom-file-button"><i class="fas fa-upload"></i> 画像を選択</label><span class="selected-file-name" id="shopProductFileName">選択されていません</span>
                            <p class="field-help">JPG・PNG・WebP、5MBまで。推奨800×800px、背景が整理された正方形画像です。</p>
                            <label class="remove-image-row" id="shopProductRemoveImageRow"><input type="checkbox" name="remove_image" value="1"> 現在の画像を削除する</label>
                        </div>
                    </div>
                </section>
            </div>
            <div class="shop-products-modal-footer">
                <div class="modal-footer-left"><span class="unsaved-warning" id="shopProductUnsavedWarning">未保存の変更が <b id="shopProductDirtyCount">0</b> 項目あります</span></div>
                <div class="submit-group"><button type="button" class="shop-products-cancel-button" data-close-product-modal>取消</button><button type="submit" class="shop-products-primary-button" id="shopProductSubmitButton">商品を登録</button></div>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset('js/admin/shop-products.js') }}"></script>
@if ($errors->any())
@php
    $oldProductData = base64_encode(json_encode([
        'id' => old('editing_product_id'), 'school_id' => old('school_id'), 'category_id' => old('category_id'),
        'product_code' => old('product_code'), 'name' => old('name'), 'description' => old('description'),
        'price' => old('price'), 'purchase_price' => old('purchase_price'), 'tax_rate' => old('tax_rate'),
        'stock_quantity' => old('stock_quantity'), 'alert_quantity' => old('alert_quantity'),
        'is_stock_managed' => old('is_stock_managed'), 'point_reward' => old('point_reward'),
        'point_price' => old('point_price'), 'barcode' => old('barcode'), 'is_online' => old('is_online'),
        'published_at' => old('published_at'), 'sales_end_at' => old('sales_end_at'),
        'display_order' => old('display_order'), 'is_active' => old('is_active'), 'image_url' => null,
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
@endphp
<script>window.addEventListener('DOMContentLoaded',()=>window.ShopProducts?.openFromValidation('{{ $oldProductData }}'));</script>
@endif
@endsection
