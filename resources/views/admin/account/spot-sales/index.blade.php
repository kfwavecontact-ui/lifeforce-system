@extends('layouts.admin')

@section('title', 'スポット売上')

@section('content')

@once
    <link rel="stylesheet" href="{{ asset('css/admin/tuition-enrollment-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/shop-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/event-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/spot-sales.css') }}">
@endonce

@php
    use Illuminate\Support\Facades\Route;
    $sortLink = function (string $key) use ($sort, $direction) {
        $nextDirection = ($sort === $key && $direction === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection, 'page' => null]);
    };
    $sortMark = function (string $key) use ($sort, $direction) {
        if ($sort !== $key) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
    $exportRouteExists = Route::has('admin.operations.classroom-accounting.spot-sales.export');
    $updateRouteBase = url('/admin/operations/classroom-accounting/spot-sales');
@endphp

<div class="tuition-sales-page shop-sales-page event-sales-page spot-sales-page" data-update-base="{{ $updateRouteBase }}">
    <div class="page-header">
        <div>
            <h1>スポット売上</h1>
            <p>単発講座、教材費、検定対策費などのスポット売上を確認します。</p>
        </div>
    </div>

    <section class="shop-sale-create-card event-sale-create-card spot-sale-create-card shop-sale-create-card-compact">
        <div class="shop-sale-create-header">
            <div>
                <h3>新規スポット売上登録</h3>
                <p>生徒、売上分類、取引名、金額を入力してスポット売上を登録します。</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.operations.classroom-accounting.spot-sales.store') }}" id="spotSaleCreateForm" class="event-sale-create-form spot-sale-create-form">
            @csrf
            <div class="event-sale-create-grid event-sale-create-grid-basic">
                <label class="shop-sale-field shop-sale-student-field">
                    <span>生徒</span>
                    <input type="text" id="spotStudentLookupInput" placeholder="生徒コード・氏名で検索" autocomplete="off">
                    <input type="hidden" name="student_id" id="spotStudentId" required>
                    <div class="tuition-student-lookup-results" id="spotStudentLookupResults"></div>
                </label>

                <label class="shop-sale-field">
                    <span>売上分類</span>
                    <select name="category" id="spotCategory">
                        <option value="単発講座">単発講座</option>
                        <option value="教材費">教材費</option>
                        <option value="検定対策">検定対策</option>
                        <option value="特別対応料">特別対応料</option>
                        <option value="その他">その他</option>
                    </select>
                </label>

                <label class="shop-sale-field event-sale-event-field spot-sale-title-field">
                    <span>取引名</span>
                    <input type="text" name="sale_title" id="spotSaleTitle" placeholder="例：単発脳開発レッスン" required>
                </label>

                <label class="shop-sale-field">
                    <span>数量</span>
                    <input type="number" name="quantity" id="spotQuantity" min="1" value="1" required>
                </label>
            </div>

            <div class="event-sale-create-grid event-sale-create-grid-payment">
                <label class="shop-sale-field"><span>取引予定日</span><input type="date" name="scheduled_date" required></label>
                <label class="shop-sale-field"><span>取引日</span><input type="date" name="transaction_date"></label>
                <label class="shop-sale-field">
                    <span>入出金方法</span>
                    <select name="payment_method_id"><option value="">未設定</option>@foreach ($paymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select>
                </label>
                <label class="shop-sale-field">
                    <span>入金状態</span>
                    <select name="payment_status" required><option value="unpaid">未入金</option><option value="paid">入金済</option><option value="cancelled">取消</option></select>
                </label>
                <label class="shop-sale-field">
                    <span>割引種別</span>
                    <select name="discount_type_id" id="spotDiscountTypeId"><option value="">割引なし</option>@foreach ($discounts as $discount)<option value="{{ $discount->id }}">{{ $discount->name }}</option>@endforeach</select>
                </label>
            </div>

            <div class="event-sale-create-grid event-sale-create-grid-amount">
                <label class="shop-sale-field"><span>割引前金額</span><input type="number" name="before_discount_amount" id="spotBeforeDiscountAmount" min="0" value="0" required></label>
                <label class="shop-sale-field"><span>割引額</span><input type="number" name="discount_amount" id="spotDiscountAmount" min="0" value="0" required></label>
                <label class="shop-sale-field event-sale-memo-field"><span>取引メモ</span><input type="text" name="memo" placeholder="取引全体の補足メモ"></label>
                <label class="shop-sale-field event-sale-memo-field"><span>割引メモ</span><input type="text" name="discount_note" placeholder="割引に関するメモ"></label>
                <div class="shop-sale-summary event-sale-summary event-sale-summary-inline">
                    <div><span>割引前金額</span><strong id="spotSubtotal">¥0</strong></div>
                    <div><span>割引</span><strong id="spotDiscountTotal">¥0</strong></div>
                    <div class="shop-sale-summary-total"><span>取引額(税込)</span><strong id="spotGrandTotal">¥0</strong></div>
                </div>
                <div class="shop-sale-actions event-sale-create-actions">
                    <button type="submit" class="shop-sale-submit-button">スポット売上を登録</button>
                    <button type="reset" class="shop-sale-clear-button">クリア</button>
                </div>
            </div>
        </form>
    </section>

    <section class="tuition-search-area event-sale-search-area spot-sale-search-area">
        <form method="GET" action="{{ route('admin.operations.classroom-accounting.spot-sales.index') }}">
            <div class="event-sale-search-grid event-sale-search-grid-dates">
                <label>取引予定日 From<input type="date" name="scheduled_from" value="{{ $filters['scheduled_from'] ?? '' }}"></label>
                <label>取引予定日 To<input type="date" name="scheduled_to" value="{{ $filters['scheduled_to'] ?? '' }}"></label>
                <label>取引日 From<input type="date" name="transaction_from" value="{{ $filters['transaction_from'] ?? '' }}"></label>
                <label>取引日 To<input type="date" name="transaction_to" value="{{ $filters['transaction_to'] ?? '' }}"></label>
                <label>教室<select name="school_id"><option value="">すべて</option>@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(($filters['school_id'] ?? '') == $school->id)>{{ $school->name }}</option>@endforeach</select></label>
            </div>
            <div class="event-sale-search-grid event-sale-search-grid-main">
                <label>売上分類<select name="category"><option value="">すべて</option>@foreach ($spotCategories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select></label>
                <label>入出金方法<select name="payment_method_id"><option value="">すべて</option>@foreach ($paymentMethods as $method)<option value="{{ $method->id }}" @selected(($filters['payment_method_id'] ?? '') == $method->id)>{{ $method->name }}</option>@endforeach</select></label>
                <label>入金状態<select name="payment_status"><option value="">すべて</option><option value="paid" @selected(($filters['payment_status'] ?? '') === 'paid')>入金済</option><option value="unpaid" @selected(($filters['payment_status'] ?? '') === 'unpaid')>未入金</option><option value="cancelled" @selected(($filters['payment_status'] ?? '') === 'cancelled')>取消</option></select></label>
                <label>割引種別<select name="discount_type_id"><option value="">すべて</option>@foreach ($discounts as $discount)<option value="{{ $discount->id }}" @selected(($filters['discount_type_id'] ?? '') == $discount->id)>{{ $discount->name }}</option>@endforeach</select></label>
                <label class="tuition-search-keyword event-sale-search-keyword">キーワード<input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="生徒名・生徒コード・取引名・取引メモ・割引メモ"></label>
                <div class="tuition-search-buttons event-sale-search-buttons">
                    <button type="submit" class="tuition-search-button">検索</button>
                    <a href="{{ route('admin.operations.classroom-accounting.spot-sales.index') }}" class="tuition-clear-button">クリア</a>
                    @if ($exportRouteExists)<a href="{{ route('admin.operations.classroom-accounting.spot-sales.export', request()->query()) }}" class="tuition-export-button">CSV出力</a>@endif
                    <button type="button" class="tuition-column-setting-button" id="tuitionColumnSettingButton"><i class="fas fa-gear"></i> 表示項目</button>
                </div>
            </div>
        </form>
    </section>

    <script>
        window.spotSalesChartData = @json($spotSalesChartData);
        window.spotCountChartData = @json($spotCountChartData);
        window.spotPaymentMethods = @json($paymentMethods->map(fn($method) => ['id' => $method->id, 'name' => $method->name])->values());
        window.spotDiscounts = @json($discounts->map(fn($discount) => ['id' => $discount->id, 'name' => $discount->name])->values());
    </script>

    <section class="tuition-table-card">
        <div class="tuition-table-summary" id="eventTableSummary" data-total-filtered="{{ $sales->total() }}" data-total-all="{{ $totalSalesCount }}">{{ number_format($sales->total()) }} 件 / 全 {{ number_format($totalSalesCount) }} 件</div>
        <div class="tuition-table-scroll">
            <table class="tuition-sales-table event-sales-table spot-sales-table">
                <thead><tr>
                    <th class="tuition-check-column"><input type="checkbox" id="tuitionCheckAll"></th>
                    <th><a href="{{ $sortLink('id') }}" class="tuition-sort-link">ID <span>{{ $sortMark('id') }}</span></a></th>
                    <th><div class="event-th-filter-wrap"><a href="{{ $sortLink('school') }}" class="tuition-sort-link">教室 <span>{{ $sortMark('school') }}</span></a><button type="button" class="event-column-filter-button" data-filter-column="3" data-filter-label="教室"><i class="fas fa-filter"></i></button></div></th>
                    <th><div class="event-th-filter-wrap"><a href="{{ $sortLink('student') }}" class="tuition-sort-link">生徒 <span>{{ $sortMark('student') }}</span></a><button type="button" class="event-column-filter-button" data-filter-column="4" data-filter-label="生徒"><i class="fas fa-filter"></i></button></div></th>
                    <th><a href="{{ $sortLink('student_code') }}" class="tuition-sort-link">生徒コード <span>{{ $sortMark('student_code') }}</span></a></th>
                    <th><div class="event-th-filter-wrap"><a href="{{ $sortLink('category') }}" class="tuition-sort-link">売上分類 <span>{{ $sortMark('category') }}</span></a><button type="button" class="event-column-filter-button" data-filter-column="6" data-filter-label="売上分類"><i class="fas fa-filter"></i></button></div></th>
                    <th><div class="event-th-filter-wrap"><a href="{{ $sortLink('sale_title') }}" class="tuition-sort-link">取引名 <span>{{ $sortMark('sale_title') }}</span></a><button type="button" class="event-column-filter-button" data-filter-column="7" data-filter-label="取引名"><i class="fas fa-filter"></i></button></div></th>
                    <th><a href="{{ $sortLink('quantity') }}" class="tuition-sort-link">数量 <span>{{ $sortMark('quantity') }}</span></a></th>
                    <th><a href="{{ $sortLink('account_category') }}" class="tuition-sort-link">会計区分 <span>{{ $sortMark('account_category') }}</span></a></th>
                    <th><a href="{{ $sortLink('scheduled_date') }}" class="tuition-sort-link">取引予定日 <span>{{ $sortMark('scheduled_date') }}</span></a></th>
                    <th><div class="event-th-filter-wrap"><a href="{{ $sortLink('payment_method') }}" class="tuition-sort-link">入出金方法 <span>{{ $sortMark('payment_method') }}</span></a><button type="button" class="event-column-filter-button" data-filter-column="11" data-filter-label="入出金方法"><i class="fas fa-filter"></i></button></div></th>
                    <th><a href="{{ $sortLink('transaction_date') }}" class="tuition-sort-link">取引日 <span>{{ $sortMark('transaction_date') }}</span></a></th>
                    <th>割引種別</th>
                    <th><a href="{{ $sortLink('before_discount_amount') }}" class="tuition-sort-link">割引前金額 <span>{{ $sortMark('before_discount_amount') }}</span></a></th>
                    <th><a href="{{ $sortLink('discount_amount') }}" class="tuition-sort-link">割引額 <span>{{ $sortMark('discount_amount') }}</span></a></th>
                    <th><a href="{{ $sortLink('amount') }}" class="tuition-sort-link">取引額(税込) <span>{{ $sortMark('amount') }}</span></a></th>
                    <th><div class="event-th-filter-wrap"><a href="{{ $sortLink('payment_status') }}" class="tuition-sort-link">入金状態 <span>{{ $sortMark('payment_status') }}</span></a><button type="button" class="event-column-filter-button" data-filter-column="17" data-filter-label="入金状態"><i class="fas fa-filter"></i></button></div></th>
                    <th>取引メモ</th><th>割引メモ</th><th>操作</th>
                </tr></thead>
                <tbody>
                @forelse ($sales as $sale)
                    <tr data-row-id="{{ $sale->id }}" data-discount-type-id="{{ $sale->discount_type_id }}" data-chart-date="{{ $sale->scheduled_date }}" data-chart-amount="{{ $sale->amount }}">
                        <td class="tuition-check-column"><input type="checkbox" class="tuition-row-check" value="{{ $sale->id }}"></td>
                        <td>{{ $sale->id }}</td><td>{{ $sale->school_name }}</td><td>{{ $sale->student_name }}</td><td>{{ $sale->student_code }}</td>
                        <td data-edit-field="category" data-value="{{ $sale->category }}"><span class="display-value">{{ $sale->category }}</span></td>
                        <td data-edit-field="sale_title" data-value="{{ $sale->sale_title }}" class="lf-tooltip event-memo-cell event-title-cell" data-tooltip="{{ $sale->sale_title }}" title="{{ $sale->sale_title }}"><span class="display-value">{{ \Illuminate\Support\Str::limit($sale->sale_title, 32) }}</span></td>
                        <td data-edit-field="quantity" data-value="{{ $sale->quantity }}"><span class="display-value">{{ $sale->quantity }}</span></td>
                        <td>{{ $sale->account_category_name }}</td>
                        <td data-edit-field="scheduled_date" data-value="{{ $sale->scheduled_date }}"><span class="display-value">{{ $sale->scheduled_date ? \Carbon\Carbon::parse($sale->scheduled_date)->format('Y/m/d') : '-' }}</span></td>
                        <td data-edit-field="payment_method_id" data-value="{{ $sale->payment_method_id }}"><span class="display-value">{{ $sale->payment_method_name }}</span></td>
                        <td data-edit-field="transaction_date" data-value="{{ $sale->transaction_date }}"><span class="display-value">{{ $sale->transaction_date ? \Carbon\Carbon::parse($sale->transaction_date)->format('Y/m/d') : '-' }}</span></td>
                        <td>{{ $sale->discount_type_name }}</td>
                        <td class="tuition-amount-cell" data-edit-field="before_discount_amount" data-value="{{ $sale->before_discount_amount }}"><span class="display-value">¥{{ number_format($sale->before_discount_amount) }}</span></td>
                        <td class="tuition-amount-cell tuition-discount-amount" data-edit-field="discount_amount" data-value="{{ $sale->discount_amount }}"><span class="display-value">¥{{ number_format($sale->discount_amount) }}</span></td>
                        <td class="tuition-amount-cell tuition-final-amount">¥{{ number_format($sale->amount) }}</td>
                        <td data-edit-field="payment_status" data-value="{{ $sale->payment_status_form_value }}"><span class="display-value tuition-status-badge {{ $sale->payment_status_class }}">{{ $sale->payment_status_label }}</span></td>
                        <td data-edit-field="memo" data-value="{{ $sale->transaction_note === '-' ? '' : $sale->transaction_note }}" class="lf-tooltip event-memo-cell" data-tooltip="{{ $sale->transaction_note }}" title="{{ $sale->transaction_note }}"><span class="display-value">{{ \Illuminate\Support\Str::limit($sale->transaction_note, 20) }}</span></td>
                        <td data-edit-field="discount_note" data-value="{{ $sale->discount_note === '-' ? '' : $sale->discount_note }}" class="lf-tooltip event-memo-cell" data-tooltip="{{ $sale->discount_note }}" title="{{ $sale->discount_note }}"><span class="display-value">{{ \Illuminate\Support\Str::limit($sale->discount_note, 20) }}</span></td>
                        <td class="tuition-action-cell">
                            <button type="button" class="tuition-detail-button" data-id="{{ $sale->id }}" data-school="{{ $sale->school_name }}" data-student="{{ $sale->student_name }}" data-student-code="{{ $sale->student_code }}" data-category="{{ $sale->category }}" data-sale-title="{{ $sale->sale_title }}" data-quantity="{{ $sale->quantity }}" data-account-category="{{ $sale->account_category_name }}" data-scheduled-date="{{ $sale->scheduled_date ? \Carbon\Carbon::parse($sale->scheduled_date)->format('Y/m/d') : '-' }}" data-payment-method="{{ $sale->payment_method_name }}" data-transaction-date="{{ $sale->transaction_date ? \Carbon\Carbon::parse($sale->transaction_date)->format('Y/m/d') : '-' }}" data-discount-type="{{ $sale->discount_type_name }}" data-before-discount="{{ number_format($sale->before_discount_amount) }}" data-discount-amount="{{ number_format($sale->discount_amount) }}" data-amount="{{ number_format($sale->amount) }}" data-payment-status="{{ $sale->payment_status_label }}" data-transaction-note="{{ $sale->transaction_note }}" data-discount-note="{{ $sale->discount_note }}" data-created-by="{{ $sale->created_by_name ?? '-' }}" data-updated-by="{{ $sale->updated_by_name ?? '-' }}">詳細</button>
                            <button type="button" class="tuition-edit-button">編集</button>
                            <button type="button" class="tuition-save-button" style="display:none;">保存</button>
                            <button type="button" class="tuition-cancel-button" style="display:none;">取消</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="20">スポット売上データがありません。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="tuition-pagination">{{ $sales->links() }}</div>
    </section>

    <section class="tuition-chart-card shop-chart-card event-chart-card spot-chart-card">
        <div class="tuition-chart-header"><div><h3>スポット売上グラフ</h3><p>表示中データに連動して売上推移と件数推移を確認します。</p></div><div class="tuition-chart-actions"><div class="tuition-chart-periods"><a href="{{ request()->fullUrlWithQuery(['chart_period' => 'all']) }}" data-chart-period="all" class="{{ $chartPeriod === 'all' ? 'active' : '' }}">全期間</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '5years']) }}" data-chart-period="5years" class="{{ $chartPeriod === '5years' ? 'active' : '' }}">直近5年</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '3years']) }}" data-chart-period="3years" class="{{ $chartPeriod === '3years' ? 'active' : '' }}">直近3年</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '1year']) }}" data-chart-period="1year" class="{{ $chartPeriod === '1year' ? 'active' : '' }}">直近1年</a></div><button type="button" id="eventChartToggle" class="tuition-chart-collapse-button">−</button></div></div>
        <div class="shop-chart-grid" id="eventChartBody"><div class="shop-chart-box"><h4>売上推移</h4><div class="shop-chart-canvas-wrap"><canvas id="spotSalesAmountChart"></canvas><div class="event-chart-empty" id="spotSalesAmountEmpty">該当する売上データがありません。</div></div></div><div class="shop-chart-box"><h4>件数推移</h4><div class="shop-chart-canvas-wrap"><canvas id="spotCountChart"></canvas><div class="event-chart-empty" id="spotCountEmpty">該当する件数データがありません。</div></div></div></div>
    </section>
</div>

<div class="event-column-filter-popover" id="eventColumnFilterPopover" aria-hidden="true"><div class="event-column-filter-title">フィルター</div><input type="text" class="event-column-filter-search" id="eventColumnFilterSearch" placeholder="検索..."><div class="event-column-filter-actions"><button type="button" id="eventColumnFilterSelectAll">すべて選択</button><button type="button" id="eventColumnFilterClear">クリア</button></div><div class="event-column-filter-list" id="eventColumnFilterList"></div><div class="event-column-filter-footer"><button type="button" id="eventColumnFilterApply">適用</button></div></div>

<div class="tuition-detail-modal" id="tuitionDetailModal"><div class="tuition-detail-content"><div class="tuition-detail-header"><h3>スポット売上詳細</h3><button type="button" id="tuitionDetailClose">×</button></div><div id="tuitionDetailBody"></div></div></div>

<div class="tuition-column-modal" id="tuitionColumnModal"><div class="tuition-column-content"><div class="tuition-column-header"><h3>表示項目設定</h3><button type="button" id="tuitionColumnClose">×</button></div><div class="tuition-column-list"><label><input type="checkbox" data-column="2" checked> 教室</label><label><input type="checkbox" data-column="3" checked> 生徒</label><label><input type="checkbox" data-column="4" checked> 生徒コード</label><label><input type="checkbox" data-column="5" checked> 売上分類</label><label><input type="checkbox" data-column="6" checked> 取引名</label><label><input type="checkbox" data-column="7" checked> 数量</label><label><input type="checkbox" data-column="10" checked> 入出金方法</label><label><input type="checkbox" data-column="12" checked> 割引種別</label><label><input type="checkbox" data-column="13" checked> 割引前金額</label><label><input type="checkbox" data-column="14" checked> 割引額</label><label><input type="checkbox" data-column="17"> 取引メモ</label><label><input type="checkbox" data-column="18"> 割引メモ</label></div></div></div>

@once
    <script src="{{ asset('js/admin/spot-sales.js') }}" defer></script>
@endonce

@endsection
