@extends('layouts.admin')

@section('title', 'ポイント商品費用')

@section('content')

@once
    <link rel="stylesheet" href="{{ asset('css/admin/tuition-enrollment-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/shop-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/event-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/spot-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/refunds.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/point-product-costs.css') }}">
@endonce

@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
    $sortLink = function (string $key) use ($sort, $direction) {
        $nextDirection = ($sort === $key && $direction === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection, 'page' => null]);
    };
    $sortMark = function (string $key) use ($sort, $direction) {
        if ($sort !== $key) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
    $yoyText = function ($rate, string $suffix = '%') {
        if ($rate === null) return '前月比 -';
        $prefix = $rate > 0 ? '+' : '';
        return '前月比 ' . $prefix . $rate . $suffix;
    };
    $exportRouteExists = Route::has('admin.operations.classroom-accounting.point-product-costs.export');
    $updateRouteBase = url('/admin/operations/classroom-accounting/point-product-costs');
@endphp

<div class="tuition-sales-page shop-sales-page event-sales-page spot-sales-page refund-page point-cost-page" data-update-base="{{ $updateRouteBase }}" data-student-search-url="{{ route('admin.operations.classroom-accounting.point-product-costs.students.search') }}">
    <div class="page-header">
        <div>
            <h1>ポイント商品費用</h1>
            <p>商品交換所で受け渡し済みになった商品の原価を、会計上の費用として確認します。</p>
        </div>
    </div>

    @if (session('success'))
        <div class="tuition-alert-success">{{ session('success') }}</div>
        <div id="pointCostToastMessage" data-message="{{ session('success') }}"></div>
    @endif

    @if ($errors->any())
        <div class="tuition-alert-error">
            <strong>入力内容を確認してください。</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="shop-sale-create-card event-sale-create-card spot-sale-create-card refund-create-card point-cost-create-card shop-sale-create-card-compact">
        <div class="shop-sale-create-header refund-create-header">
            <div>
                <h3>新規ポイント商品費用登録</h3>
                <p>生徒とポイント商品を選択し、受け渡し済みの場合は会計台帳へ費用計上します。</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.operations.classroom-accounting.point-product-costs.store') }}" id="pointCostCreateForm" class="event-sale-create-form refund-create-form refund-create-form-compact point-cost-create-form">
            @csrf
            <input type="hidden" name="student_id" id="pointCostStudentId">

            <div class="refund-create-two-line-grid point-cost-create-grid">
                <label class="shop-sale-field point-cost-student-field">
                    <span>生徒</span>
                    <input type="text" id="pointCostStudentLookupInput" placeholder="生徒コード・氏名で検索" autocomplete="off" required>
                    <div class="event-student-search-results" id="pointCostStudentResults"></div>
                </label>

                <label class="shop-sale-field point-cost-item-field">
                    <span>商品</span>
                    <input type="text" id="pointCostRewardItemLookupInput" placeholder="商品名・カテゴリで検索" autocomplete="off" required>
                    <input type="hidden" name="reward_item_id" id="pointCostRewardItem" required>
                    <div class="event-student-search-results point-cost-item-results" id="pointCostRewardItemResults"></div>
                </label>

                <label class="shop-sale-field">
                    <span>状態</span>
                    <select name="status" id="pointCostStatus" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="shop-sale-field">
                    <span>申請日</span>
                    <input type="date" name="requested_at" id="pointCostRequestedAt" value="{{ now()->toDateString() }}" required>
                </label>

                <label class="shop-sale-field">
                    <span>受け渡し日</span>
                    <input type="date" name="delivered_at" id="pointCostDeliveredAt">
                </label>

                <label class="shop-sale-field point-cost-memo-field">
                    <span>メモ</span>
                    <input type="text" name="note" placeholder="例：教室で受け渡し済み">
                </label>
            </div>

            <div class="refund-create-bottom-row">
                <div class="refund-total-box refund-total-box-inline point-cost-total-box">
                    <div><span>カテゴリ</span><strong id="pointCostCategoryText">未選択</strong></div>
                    <div><span>使用ポイント</span><strong id="pointCostPointsText">0pt</strong></div>
                    <div><span>商品原価</span><strong id="pointCostAmountText">¥0</strong></div>
                    <div><span>在庫数</span><strong id="pointCostStockText">0個</strong></div>
                    <div><span>現在ポイント</span><strong id="pointCostCurrentPointsText">未選択</strong></div>
                    <div><span>累計獲得</span><strong id="pointCostTotalEarnedText">未選択</strong></div>
                </div>
                <button type="submit" class="tuition-search-button refund-submit-button" id="pointCostSubmitButton">ポイント商品費用を登録</button>
            </div>
        </form>
    </section>

    <section class="tuition-filter-card shop-filter-card event-filter-card spot-filter-card refund-filter-card point-cost-filter-card">
        <form method="GET" action="{{ route('admin.operations.classroom-accounting.point-product-costs.index') }}" class="spot-sale-search-form refund-search-form point-cost-search-form">
            <div class="spot-sale-search-grid refund-search-grid point-cost-search-grid">
                <label>申請日 From<input type="date" name="requested_from" value="{{ $filters['requested_from'] ?? '' }}"></label>
                <label>申請日 To<input type="date" name="requested_to" value="{{ $filters['requested_to'] ?? '' }}"></label>
                <label>受け渡し日 From<input type="date" name="delivered_from" value="{{ $filters['delivered_from'] ?? '' }}"></label>
                <label>受け渡し日 To<input type="date" name="delivered_to" value="{{ $filters['delivered_to'] ?? '' }}"></label>
                <label>教室<select name="school_id"><option value="">すべて</option>@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(($filters['school_id'] ?? '') == $school->id)>{{ $school->name }}</option>@endforeach</select></label>

                <label>カテゴリ<select name="reward_category_id"><option value="">すべて</option>@foreach ($rewardCategories as $category)<option value="{{ $category->id }}" @selected(($filters['reward_category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <label>商品<select name="reward_item_id"><option value="">すべて</option>@foreach ($rewardItems as $item)<option value="{{ $item->id }}" @selected(($filters['reward_item_id'] ?? '') == $item->id)>{{ $item->name }}</option>@endforeach</select></label>
                <label>状態<select name="status"><option value="">すべて</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>会計反映<select name="account_sync_status"><option value="">すべて</option><option value="synced" @selected(($filters['account_sync_status'] ?? '') === 'synced')>反映済み</option><option value="unsynced" @selected(($filters['account_sync_status'] ?? '') === 'unsynced')>未反映</option></select></label>
                <label class="refund-keyword-field">キーワード<input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="生徒・商品名・カテゴリ・メモ"></label>

                <div class="tuition-search-buttons event-sale-search-buttons refund-search-actions point-cost-search-actions">
                    <button type="submit" class="tuition-search-button">検索</button>
                    <a href="{{ route('admin.operations.classroom-accounting.point-product-costs.index') }}" class="tuition-clear-button">クリア</a>
                    @if ($exportRouteExists)
                        <a href="{{ route('admin.operations.classroom-accounting.point-product-costs.export', request()->query()) }}" class="tuition-export-button">CSV出力</a>
                    @endif
                    <button type="button" id="tuitionColumnButton" class="tuition-column-setting-button"><i class="fas fa-gear"></i> 表示項目</button>
                </div>
            </div>
        </form>
    </section>

    <section class="tuition-table-card shop-table-card event-table-card spot-table-card refund-table-card point-cost-table-card">
        <div class="tuition-table-header point-cost-table-header-count-only">
            <div>
                <p>表示中 {{ number_format($costs->count()) }}件 / 全 {{ number_format($costs->total()) }}件</p>
            </div>
        </div>

        <div class="tuition-table-scroll point-cost-table-scroll">
            <table class="tuition-table event-sales-table refund-table point-cost-table" id="pointCostTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="tuitionCheckAll"></th>
                        <th><a href="{{ $sortLink('id') }}">ID {{ $sortMark('id') }}</a></th>
                        <th><a href="{{ $sortLink('school') }}">教室 {{ $sortMark('school') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="school">▼</button></th>
                        <th><a href="{{ $sortLink('student') }}">生徒 {{ $sortMark('student') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="student">▼</button></th>
                        <th><a href="{{ $sortLink('student_code') }}">生徒コード {{ $sortMark('student_code') }}</a></th>
                        <th><a href="{{ $sortLink('category') }}">カテゴリ {{ $sortMark('category') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="category">▼</button></th>
                        <th><a href="{{ $sortLink('item') }}">商品名 {{ $sortMark('item') }}</a></th>
                        <th><a href="{{ $sortLink('points') }}">使用Pt {{ $sortMark('points') }}</a></th>
                        <th><a href="{{ $sortLink('cost') }}">原価 {{ $sortMark('cost') }}</a></th>
                        <th><a href="{{ $sortLink('status') }}">状態 {{ $sortMark('status') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="status">▼</button></th>
                        <th><a href="{{ $sortLink('requested_at') }}">申請日 {{ $sortMark('requested_at') }}</a></th>
                        <th><a href="{{ $sortLink('delivered_at') }}">受け渡し日 {{ $sortMark('delivered_at') }}</a></th>
                        <th><a href="{{ $sortLink('account_sync') }}">会計反映 {{ $sortMark('account_sync') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="account">▼</button></th>
                        <th>メモ</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($costs as $cost)
                    <tr data-row-id="{{ $cost->id }}">
                        <td><input type="checkbox" class="tuition-row-check" value="{{ $cost->id }}"></td>
                        <td>{{ $cost->id }}</td>
                        <td class="lf-tooltip point-cost-school-cell" data-filter-school="{{ $cost->school_name }}" data-tooltip="{{ $cost->school_name }}" title="{{ $cost->school_name }}"><span class="point-cost-ellipsis">{{ $cost->school_name }}</span></td>
                        <td data-filter-student="{{ $cost->student_name }}">{{ $cost->student_name }}</td>
                        <td>{{ $cost->student_code }}</td>
                        <td class="lf-tooltip point-cost-category-cell" data-filter-category="{{ $cost->reward_category_name }}" data-tooltip="{{ $cost->reward_category_name }}" title="{{ $cost->reward_category_name }}"><span class="point-cost-ellipsis">{{ $cost->reward_category_name }}</span></td>
                        <td class="point-cost-item-cell lf-tooltip" data-tooltip="{{ $cost->reward_item_name }}" title="{{ $cost->reward_item_name }}"><span class="point-cost-ellipsis">{{ $cost->reward_item_name }}</span></td>
                        <td class="tuition-amount-cell">{{ number_format($cost->request_points) }}pt</td>
                        <td class="tuition-amount-cell">¥{{ number_format($cost->cost_price) }}</td>
                        <td data-filter-status="{{ $cost->status_label }}" data-edit-field="status" data-value="{{ $cost->status }}"><span class="display-value tuition-status-badge {{ $cost->status_class }}">{{ $cost->status_label }}</span></td>
                        <td data-edit-field="requested_at" data-value="{{ $cost->requested_at }}"><span class="display-value">{{ $cost->requested_at ? \Carbon\Carbon::parse($cost->requested_at)->format('Y/m/d') : '-' }}</span></td>
                        <td data-edit-field="delivered_at" data-value="{{ $cost->delivered_at }}"><span class="display-value">{{ $cost->delivered_at ? \Carbon\Carbon::parse($cost->delivered_at)->format('Y/m/d') : '-' }}</span></td>
                        <td data-filter-account="{{ $cost->account_sync_label }}"><span class="tuition-status-badge {{ $cost->account_sync_class }}">{{ $cost->account_sync_label }}</span></td>
                        <td data-edit-field="note" data-value="{{ $cost->note === '-' ? '' : $cost->note }}" class="lf-tooltip event-memo-cell" data-tooltip="{{ $cost->note }}" title="{{ $cost->note }}"><span class="display-value">{{ Str::limit($cost->note, 20) }}</span></td>
                        <td class="tuition-action-cell">
                            <button type="button" class="tuition-detail-button"
                                data-id="{{ $cost->id }}" data-school="{{ $cost->school_name }}" data-student="{{ $cost->student_name }}" data-student-code="{{ $cost->student_code }}" data-category="{{ $cost->reward_category_name }}" data-item="{{ $cost->reward_item_name }}" data-points="{{ number_format($cost->request_points) }}" data-cost="{{ number_format($cost->cost_price) }}" data-status="{{ $cost->status_label }}" data-requested-at="{{ $cost->requested_at ? \Carbon\Carbon::parse($cost->requested_at)->format('Y/m/d') : '-' }}" data-approved-at="{{ $cost->approved_at ? \Carbon\Carbon::parse($cost->approved_at)->format('Y/m/d') : '-' }}" data-delivered-at="{{ $cost->delivered_at ? \Carbon\Carbon::parse($cost->delivered_at)->format('Y/m/d') : '-' }}" data-rejected-at="{{ $cost->rejected_at ? \Carbon\Carbon::parse($cost->rejected_at)->format('Y/m/d') : '-' }}" data-account-sync="{{ $cost->account_sync_label }}" data-note="{{ $cost->note }}" data-created-at="{{ $cost->created_at_display ?? '-' }}" data-updated-at="{{ $cost->updated_at_display ?? '-' }}" data-account-transaction-id="{{ $cost->account_transaction_id ?? '-' }}" data-account-created-at="{{ $cost->account_created_at_display ?? '-' }}" data-account-updated-at="{{ $cost->account_updated_at_display ?? '-' }}" data-source-table="point_exchange" data-source-id="{{ $cost->id }}">詳細</button>
                            <button type="button" class="tuition-edit-button">編集</button>
                            <button type="button" class="tuition-save-button" style="display:none;">保存</button>
                            <button type="button" class="tuition-cancel-button" style="display:none;">取消</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15">ポイント商品費用データがありません。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="tuition-pagination">{{ $costs->links() }}</div>
    </section>

    <section class="tuition-chart-card shop-chart-card event-chart-card spot-chart-card refund-chart-card point-cost-chart-card">
        <div class="tuition-chart-header"><div><h3>ポイント商品費用グラフ</h3><p>表示中データに連動して費用推移・件数推移・商品カテゴリを確認します。</p></div><div class="tuition-chart-actions"><div class="tuition-chart-periods"><a href="{{ request()->fullUrlWithQuery(['chart_period' => 'all']) }}" class="{{ $chartPeriod === 'all' ? 'active' : '' }}">全期間</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '5years']) }}" class="{{ $chartPeriod === '5years' ? 'active' : '' }}">直近5年</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '3years']) }}" class="{{ $chartPeriod === '3years' ? 'active' : '' }}">直近3年</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '1year']) }}" class="{{ $chartPeriod === '1year' ? 'active' : '' }}">直近1年</a></div><button type="button" id="eventChartToggle" class="tuition-chart-collapse-button">−</button></div></div>
        <div class="shop-chart-grid refund-chart-grid" id="eventChartBody">
            <div class="shop-chart-box"><h4>費用額推移</h4><div class="shop-chart-canvas-wrap"><canvas id="pointCostAmountChart"></canvas><div class="event-chart-empty" id="pointCostAmountEmpty">該当する費用データがありません。</div></div></div>
            <div class="shop-chart-box"><h4>件数推移</h4><div class="shop-chart-canvas-wrap"><canvas id="pointCostCountChart"></canvas><div class="event-chart-empty" id="pointCostCountEmpty">該当する件数データがありません。</div></div></div>
        </div>
    </section>
</div>

<div class="tuition-detail-modal" id="tuitionDetailModal"><div class="tuition-detail-content refund-detail-content"><div class="tuition-detail-header"><h3>ポイント商品費用詳細</h3><button type="button" id="tuitionDetailClose">×</button></div><div id="tuitionDetailBody"></div></div></div>

<div class="tuition-column-modal" id="tuitionColumnModal"><div class="tuition-column-content"><div class="tuition-column-header"><h3>表示項目設定</h3><button type="button" id="tuitionColumnClose">×</button></div><div class="tuition-column-list"><label><input type="checkbox" data-column="2" checked> 教室</label><label><input type="checkbox" data-column="3" checked> 生徒</label><label><input type="checkbox" data-column="4" checked> 生徒コード</label><label><input type="checkbox" data-column="5" checked> カテゴリ</label><label><input type="checkbox" data-column="6" checked> 商品名</label><label><input type="checkbox" data-column="7" checked> 使用Pt</label><label><input type="checkbox" data-column="8" checked> 原価</label><label><input type="checkbox" data-column="12" checked> 会計反映</label><label><input type="checkbox" data-column="13"> メモ</label></div></div></div>

<script>
    window.pointCostAmountChartData = @json($costAmountChartData);
    window.pointCostCountChartData = @json($costCountChartData);
    window.pointCostCategoryChartData = @json($categoryChartData);
    window.pointCostStatuses = @json(collect($statuses)->map(fn($label, $value) => ['value' => $value, 'label' => $label])->values());
    window.pointCostRewardItems = {!! json_encode(
        $rewardItems->map(function ($item) use ($rewardCategories) {
            $categoryName = optional($rewardCategories->firstWhere('id', $item->reward_category_id))->name ?? '未分類';

            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $categoryName,
                'required_points' => (int) $item->required_points,
                'cost_price' => (int) ($item->cost_price ?? 0),
                'stock_quantity' => (int) ($item->stock_quantity ?? 0),
            ];
        })->values(),
        JSON_UNESCAPED_UNICODE
    ) !!};
</script>

@once
    <script src="{{ asset('js/admin/point-product-costs.js') }}" defer></script>
@endonce

@endsection
