@extends('layouts.admin')

@section('title', 'ポイント商品管理')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/point-product-management.css') }}">
@endpush

@section('content')
<div
    class="ppm-page"
    data-list-url="{{ route('admin.system.point-products.list') }}"
    data-store-url="{{ route('admin.system.point-products.store') }}"
    data-base-url="{{ url('/admin/system/point-products') }}"
    data-reorder-url="{{ route('admin.system.point-products.reorder') }}"
    data-csrf="{{ csrf_token() }}"
>
    <header class="ppm-header">
        <div>
            <h1>ポイント商品管理</h1>
            <p>商品情報・必要ポイント・在庫・公開状態・画像を管理します。</p>
        </div>
        <button id="ppmAdd" class="ppm-primary" type="button">＋ 商品を追加</button>
    </header>

    <section class="ppm-stats" aria-label="ポイント商品集計">
        <button type="button" data-stat-filter="all"><strong id="ppmRegistered">0</strong><span>登録数</span></button>
        <button type="button" data-stat-filter="published"><strong id="ppmPublished">0</strong><span>公開中</span></button>
        <button type="button" data-stat-filter="out"><strong id="ppmOutStock">0</strong><span>在庫切れ</span></button>
        <button type="button" data-stat-filter="low"><strong id="ppmLowStock">0</strong><span>在庫注意</span></button>
        <button type="button" data-stat-filter="limited"><strong id="ppmLimited">0</strong><span>限定商品</span></button>
        <button type="button" data-stat-filter="ended"><strong id="ppmEnded">0</strong><span>公開終了</span></button>
    </section>

    <section class="ppm-filter">
        <input id="ppmKeyword" type="search" placeholder="商品コード・商品名・説明で検索">
        <select id="ppmCategory">
            <option value="">すべてのカテゴリ</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
        <select id="ppmStatus">
            <option value="">すべての公開状態</option>
            <option value="draft">下書き</option>
            <option value="published">公開</option>
            <option value="ended">公開終了</option>
        </select>
        <select id="ppmStock">
            <option value="">すべての在庫</option>
            <option value="available">交換可能</option>
            <option value="low">在庫注意</option>
            <option value="out">在庫切れ</option>
            <option value="unmanaged">在庫管理なし</option>
        </select>
        <select id="ppmRecommended">
            <option value="">おすすめ：すべて</option>
            <option value="1">おすすめのみ</option>
            <option value="0">おすすめ以外</option>
        </select>
        <select id="ppmLimitedFilter">
            <option value="">限定：すべて</option>
            <option value="1">限定のみ</option>
            <option value="0">限定以外</option>
        </select>
        <select id="ppmActive">
            <option value="">有効状態：すべて</option>
            <option value="1">有効</option>
            <option value="0">無効</option>
        </select>
        <select id="ppmSort">
            <option value="display_order">表示順</option>
            <option value="updated_desc">更新日が新しい順</option>
            <option value="points_asc">必要ポイントが少ない順</option>
            <option value="points_desc">必要ポイントが多い順</option>
            <option value="name_asc">商品名順</option>
        </select>
        <button id="ppmSearch" class="ppm-secondary" type="button">検索</button>
        <button id="ppmReset" class="ppm-ghost" type="button">クリア</button>
    </section>

    <section class="ppm-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>順</th>
                    <th>ID</th>
                    <th>画像</th>
                    <th>商品コード</th>
                    <th>商品名</th>
                    <th>カテゴリ</th>
                    <th class="ppm-number">必要Pt</th>
                    <th class="ppm-number">実在庫</th>
                    <th class="ppm-number">予約</th>
                    <th class="ppm-number">交換可能</th>
                    <th>公開状態</th>
                    <th>おすすめ</th>
                    <th>限定</th>
                    <th>有効</th>
                    <th>更新日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="ppmBody">
                <tr><td colspan="16">読み込み中...</td></tr>
            </tbody>
        </table>
    </section>

    <div id="ppmPagination" class="ppm-pagination"></div>
</div>

<div class="ppm-overlay" id="ppmOverlay"></div>
<aside class="ppm-drawer" id="ppmDrawer" aria-hidden="true">
    <header class="ppm-drawer-header">
        <div>
            <span class="ppm-eyebrow">ポイント商品</span>
            <h2 id="ppmDrawerTitle">商品を追加</h2>
        </div>
        <button id="ppmClose" class="ppm-close" type="button" aria-label="閉じる">×</button>
    </header>

    <form id="ppmForm" enctype="multipart/form-data">
        <input type="hidden" id="ppmId">

        <section class="ppm-form-section">
            <h3>基本情報</h3>
            <div class="ppm-grid">
                <label>商品コード <span>*</span><input name="code" required maxlength="50"></label>
                <label>商品名 <span>*</span><input name="name" required maxlength="150"></label>
                <label>カテゴリ <span>*</span>
                    <select name="reward_category_id" required>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>表示順<input type="number" name="sort_order" min="1"></label>
            </div>
        </section>

        <section class="ppm-form-section">
            <h3>ポイント設定</h3>
            <div class="ppm-grid">
                <label>必要ポイント <span>*</span><input type="number" name="required_points" min="0" required></label>
                <label>原価<input type="number" name="cost_price" min="0"></label>
                <label>生徒別交換上限<input type="number" name="exchange_limit_per_student" min="1"></label>
                <label>月間交換上限<input type="number" name="exchange_limit_per_month" min="1"></label>
            </div>
        </section>

        <section class="ppm-form-section">
            <h3>在庫設定</h3>
            <div class="ppm-check-row"><label><input type="checkbox" name="is_stock_managed" checked> 在庫を管理する</label></div>
            <div class="ppm-grid">
                <label>実在庫 <span>*</span><input type="number" name="stock_quantity" min="0" value="0" required></label>
                <label>予約数<input type="number" name="reserved_quantity" min="0" value="0"></label>
                <label>在庫注意数 <span>*</span><input type="number" name="alert_quantity" min="0" value="0" required></label>
                <label>交換可能数<input id="ppmAvailablePreview" type="text" value="0" readonly></label>
            </div>
        </section>

        <section class="ppm-form-section">
            <h3>公開設定</h3>
            <div class="ppm-grid">
                <label>公開状態 <span>*</span>
                    <select name="publication_status" required>
                        <option value="draft">下書き</option>
                        <option value="published">公開</option>
                        <option value="ended">公開終了</option>
                    </select>
                </label>
                <label>公開開始<input type="datetime-local" name="published_at"></label>
                <label>公開終了<input type="datetime-local" name="publication_ended_at"></label>
            </div>
            <div class="ppm-checks">
                <label><input type="checkbox" name="is_recommended"> おすすめ</label>
                <label><input type="checkbox" name="is_new"> NEW</label>
                <label><input type="checkbox" name="is_limited"> 限定</label>
                <label><input type="checkbox" name="is_active" checked> 有効</label>
            </div>
        </section>

        <section class="ppm-form-section">
            <h3>画像</h3>
            <label class="ppm-file-label">商品画像（最大5MB・複数可）
                <input id="ppmImages" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
            </label>
            <p class="ppm-help">登録済み画像は「メイン画像にする」または「削除予定」にできます。</p>
            <div id="ppmImageList" class="ppm-image-list"></div>
            <div id="ppmNewImageList" class="ppm-image-list"></div>
        </section>

        <section class="ppm-form-section">
            <h3>詳細説明</h3>
            <label class="ppm-wide">商品説明<textarea name="description" rows="6" maxlength="3000"></textarea></label>
        </section>

        <footer class="ppm-drawer-footer">
            <button type="button" id="ppmCancel" class="ppm-ghost">取消</button>
            <button class="ppm-primary" type="submit">保存</button>
        </footer>
    </form>
</aside>

<div class="ppm-lightbox" id="ppmLightbox" aria-hidden="true">
    <button type="button" id="ppmLightboxClose" aria-label="画像を閉じる">×</button>
    <img id="ppmLightboxImage" alt="商品画像拡大表示">
</div>
@endsection
