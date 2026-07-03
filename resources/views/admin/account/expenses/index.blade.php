@extends('layouts.admin')

@section('title', '経費')

@section('content')

@once
    <link rel="stylesheet" href="{{ asset('css/admin/tuition-enrollment-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/shop-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/event-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/spot-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/refunds.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/point-product-costs.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/expenses.css') }}">
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
    $exportRouteExists = Route::has('admin.operations.classroom-accounting.expenses.export');
    $updateRouteBase = url('/admin/operations/classroom-accounting/expenses');
@endphp

<div class="tuition-sales-page shop-sales-page event-sales-page spot-sales-page refund-page point-cost-page expense-page" data-update-base="{{ $updateRouteBase }}">
    <div class="page-header">
        <div>
            <h1>経費</h1>
            <p>教室運営で発生した経費を登録し、会計台帳へ費用として連動します。</p>
        </div>
    </div>

    @if (session('success'))
        <div class="tuition-alert-success">{{ session('success') }}</div>
        <div id="expenseToastMessage" data-message="{{ session('success') }}"></div>
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

    <section class="shop-sale-create-card event-sale-create-card spot-sale-create-card refund-create-card point-cost-create-card expense-create-card shop-sale-create-card-compact">
        <div class="shop-sale-create-header refund-create-header">
            <div>
                <h3>新規経費登録</h3>
                <p>支払予定日・経費分類・支払先・金額を入力します。支払済にすると支払日が会計台帳の取引日になります。</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.operations.classroom-accounting.expenses.store') }}" id="expenseCreateForm" class="event-sale-create-form refund-create-form refund-create-form-compact point-cost-create-form expense-create-form">
            @csrf
            <div class="refund-create-two-line-grid point-cost-create-grid expense-create-grid">
                <label class="shop-sale-field"><span>教室</span><select name="school_id" required>@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>@endforeach</select></label>
                <label class="shop-sale-field"><span>経費分類</span><select name="expense_category" required>@foreach ($expenseCategories as $category)<option value="{{ $category['value'] }}" @selected(old('expense_category') === $category['value'])>{{ $category['label'] }}</option>@endforeach</select></label>
                <label class="shop-sale-field expense-title-field"><span>経費名</span><input type="text" name="expense_title" value="{{ old('expense_title') }}" placeholder="例：教室用コピー用紙" required></label>
                <label class="shop-sale-field"><span>支払先</span><input type="text" name="vendor_name" value="{{ old('vendor_name') }}" placeholder="例：オフィス用品店"></label>

                <label class="shop-sale-field"><span>支払予定日</span><input type="date" name="scheduled_date" value="{{ old('scheduled_date', now()->toDateString()) }}" required></label>
                <label class="shop-sale-field"><span>支払日</span><input type="date" name="paid_at" value="{{ old('paid_at') }}"></label>
                <label class="shop-sale-field"><span>支払方法</span><select name="payment_method_id"><option value="">未選択</option>@foreach ($paymentMethods as $method)<option value="{{ $method->id }}" @selected(old('payment_method_id') == $method->id)>{{ $method->name }}</option>@endforeach</select></label>
                <label class="shop-sale-field"><span>支払状況</span><select name="payment_status" required>@foreach ($expenseStatuses as $status)<option value="{{ $status['value'] }}" @selected(old('payment_status', 'unpaid') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>

                <label class="shop-sale-field"><span>税抜金額</span><input type="number" name="amount" min="0" step="1" value="{{ old('amount', 0) }}" required></label>
                <label class="shop-sale-field"><span>消費税</span><input type="number" name="tax_amount" min="0" step="1" value="{{ old('tax_amount', 0) }}" required></label>
                <label class="shop-sale-field expense-memo-field"><span>経費メモ</span><input type="text" name="memo" value="{{ old('memo') }}" placeholder="補足があれば入力"></label>
            </div>

            <div class="refund-create-bottom-row expense-create-bottom-row">
                <div class="refund-total-box refund-total-box-inline point-cost-total-box expense-total-box">
                    <div><span>税込金額</span><strong id="expenseCreateTotalAmount">¥0</strong></div>
                    <div><span>税抜金額</span><strong id="expenseCreateAmountText">¥0</strong></div>
                    <div><span>消費税</span><strong id="expenseCreateTaxText">¥0</strong></div>
                    <div><span>状態</span><strong id="expenseCreateStatusText">未払い</strong></div>
                </div>
                <button type="submit" class="tuition-search-button refund-submit-button">経費を登録</button>
            </div>
        </form>
    </section>

    <section class="tuition-filter-card shop-filter-card event-filter-card spot-filter-card refund-filter-card point-cost-filter-card expense-filter-card">
        <form method="GET" action="{{ route('admin.operations.classroom-accounting.expenses.index') }}" class="spot-sale-search-form refund-search-form point-cost-search-form expense-search-form">
            <div class="spot-sale-search-grid refund-search-grid point-cost-search-grid expense-search-grid">
                <label>支払予定日 From<input type="date" name="scheduled_from" value="{{ $filters['scheduled_from'] ?? '' }}"></label>
                <label>支払予定日 To<input type="date" name="scheduled_to" value="{{ $filters['scheduled_to'] ?? '' }}"></label>
                <label>支払日 From<input type="date" name="paid_from" value="{{ $filters['paid_from'] ?? '' }}"></label>
                <label>支払日 To<input type="date" name="paid_to" value="{{ $filters['paid_to'] ?? '' }}"></label>
                <label>教室<select name="school_id"><option value="">すべて</option>@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(($filters['school_id'] ?? '') == $school->id)>{{ $school->name }}</option>@endforeach</select></label>

                <label>経費分類<select name="expense_category"><option value="">すべて</option>@foreach ($expenseCategories as $category)<option value="{{ $category['value'] }}" @selected(($filters['expense_category'] ?? '') === $category['value'])>{{ $category['label'] }}</option>@endforeach</select></label>
                <label>支払方法<select name="payment_method_id"><option value="">すべて</option>@foreach ($paymentMethods as $method)<option value="{{ $method->id }}" @selected(($filters['payment_method_id'] ?? '') == $method->id)>{{ $method->name }}</option>@endforeach</select></label>
                <label>支払状況<select name="payment_status"><option value="">すべて</option>@foreach ($expenseStatuses as $status)<option value="{{ $status['value'] }}" @selected(($filters['payment_status'] ?? '') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
                <label class="refund-keyword-field expense-keyword-field">キーワード<input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="経費名・支払先・メモ"></label>

                <div class="tuition-search-buttons event-sale-search-buttons refund-search-actions point-cost-search-actions expense-search-actions">
                    <button type="submit" class="tuition-search-button">検索</button>
                    <a href="{{ route('admin.operations.classroom-accounting.expenses.index') }}" class="tuition-clear-button">クリア</a>
                    @if ($exportRouteExists)
                        <a href="{{ route('admin.operations.classroom-accounting.expenses.export', request()->query()) }}" class="tuition-export-button">CSV出力</a>
                    @endif
                    <button type="button" id="expenseColumnButton" class="tuition-column-setting-button"><i class="fas fa-gear"></i> 表示項目</button>
                </div>
            </div>
        </form>
    </section>

    <section class="tuition-table-card shop-table-card event-table-card spot-table-card refund-table-card point-cost-table-card expense-table-card">
        <div class="tuition-table-header point-cost-table-header-count-only expense-table-header-count-only">
            <div><p>表示中 {{ number_format($expenses->count()) }}件 / 全 {{ number_format($expenses->total()) }}件</p></div>
        </div>

        <div class="tuition-table-scroll point-cost-table-scroll expense-table-scroll">
            <table class="tuition-table event-sales-table refund-table point-cost-table expense-table" id="expenseTable">
                <colgroup>
                    <col class="expense-col-check">
                    <col class="expense-col-id">
                    <col class="expense-col-school">
                    <col class="expense-col-category">
                    <col class="expense-col-title">
                    <col class="expense-col-vendor">
                    <col class="expense-col-scheduled">
                    <col class="expense-col-paid">
                    <col class="expense-col-method">
                    <col class="expense-col-amount">
                    <col class="expense-col-tax">
                    <col class="expense-col-total">
                    <col class="expense-col-status">
                    <col class="expense-col-creator">
                    <col class="expense-col-memo">
                    <col class="expense-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th class="expense-col-check"><input type="checkbox" id="expenseCheckAll"></th>
                        <th class="expense-col-id"><a href="{{ $sortLink('id') }}">ID {{ $sortMark('id') }}</a></th>
                        <th class="expense-col-school"><a href="{{ $sortLink('school') }}">教室 {{ $sortMark('school') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="school">▼</button></th>
                        <th class="expense-col-category"><a href="{{ $sortLink('expense_category') }}">経費分類 {{ $sortMark('expense_category') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="category">▼</button></th>
                        <th class="expense-col-title"><a href="{{ $sortLink('expense_title') }}">経費名 {{ $sortMark('expense_title') }}</a></th>
                        <th class="expense-col-vendor"><a href="{{ $sortLink('vendor_name') }}">支払先 {{ $sortMark('vendor_name') }}</a></th>
                        <th class="expense-col-scheduled"><a href="{{ $sortLink('scheduled_date') }}">支払予定日 {{ $sortMark('scheduled_date') }}</a></th>
                        <th class="expense-col-paid"><a href="{{ $sortLink('paid_at') }}">支払日 {{ $sortMark('paid_at') }}</a></th>
                        <th class="expense-col-method">支払方法 <button type="button" class="refund-column-filter-button" data-filter-col="method">▼</button></th>
                        <th class="expense-col-amount"><a href="{{ $sortLink('amount') }}">税抜 {{ $sortMark('amount') }}</a></th>
                        <th class="expense-col-tax">消費税</th>
                        <th class="expense-col-total"><a href="{{ $sortLink('total_amount') }}">税込金額 {{ $sortMark('total_amount') }}</a></th>
                        <th class="expense-col-status"><a href="{{ $sortLink('payment_status') }}">支払状況 {{ $sortMark('payment_status') }}</a><button type="button" class="refund-column-filter-button" data-filter-col="status">▼</button></th>
                        <th class="expense-col-creator">登録者</th>
                        <th class="expense-col-memo">経費メモ</th>
                        <th class="expense-col-actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($expenses as $expense)
                    <tr data-row-id="{{ $expense->id }}">
                        <td class="expense-col-check"><input type="checkbox" class="expense-row-check" value="{{ $expense->id }}"></td>
                        <td class="expense-col-id">{{ $expense->id }}</td>
                        <td class="expense-col-school" data-filter-school="{{ $expense->school_name }}" data-edit-field="school_id" data-value="{{ $expense->school_id }}"><span class="display-value">{{ $expense->school_name }}</span></td>
                        <td class="expense-col-category" data-filter-category="{{ $expense->expense_category_label }}" data-edit-field="expense_category" data-value="{{ $expense->expense_category_value }}"><span class="display-value expense-category-badge">{{ $expense->expense_category_label }}</span></td>
                        <td class="expense-col-title lf-tooltip expense-title-cell" data-edit-field="expense_title" data-value="{{ $expense->expense_title }}" data-tooltip="{{ $expense->expense_title }}" title="{{ $expense->expense_title }}"><span class="display-value expense-ellipsis">{{ $expense->expense_title }}</span></td>
                        <td class="expense-col-vendor lf-tooltip expense-vendor-cell" data-edit-field="vendor_name" data-value="{{ $expense->vendor_name }}" data-tooltip="{{ $expense->vendor_name ?: '-' }}" title="{{ $expense->vendor_name ?: '-' }}"><span class="display-value expense-ellipsis">{{ $expense->vendor_name ?: '-' }}</span></td>
                        <td class="expense-col-scheduled" data-edit-field="scheduled_date" data-value="{{ $expense->scheduled_date_input }}"><span class="display-value">{{ $expense->scheduled_date_text }}</span></td>
                        <td class="expense-col-paid" data-edit-field="paid_at" data-value="{{ $expense->paid_at_input }}"><span class="display-value">{{ $expense->paid_at_text }}</span></td>
                        <td class="expense-col-method" data-filter-method="{{ $expense->payment_method_name }}" data-edit-field="payment_method_id" data-value="{{ $expense->payment_method_id }}"><span class="display-value">{{ $expense->payment_method_name }}</span></td>
                        <td class="expense-col-amount tuition-amount-cell" data-edit-field="amount" data-value="{{ (int) $expense->amount }}"><span class="display-value">¥{{ number_format($expense->amount) }}</span></td>
                        <td class="expense-col-tax tuition-amount-cell" data-edit-field="tax_amount" data-value="{{ (int) $expense->tax_amount }}"><span class="display-value">¥{{ number_format($expense->tax_amount) }}</span></td>
                        <td class="expense-col-total tuition-amount-cell"><span class="expense-total-display">¥{{ number_format($expense->total_amount) }}</span></td>
                        <td class="expense-col-status" data-filter-status="{{ $expense->payment_status_label }}" data-edit-field="payment_status" data-value="{{ $expense->payment_status_value }}"><span class="display-value tuition-status-badge expense-status-badge expense-status-{{ $expense->payment_status_value }}">{{ $expense->payment_status_label }}</span></td>
                        <td class="expense-col-creator">{{ $expense->creator_name }}</td>
                        <td class="expense-col-memo lf-tooltip event-memo-cell expense-memo-cell" data-edit-field="memo" data-value="{{ $expense->memo ?? '' }}" data-tooltip="{{ $expense->memo ?: '-' }}" title="{{ $expense->memo ?: '-' }}"><span class="display-value">{{ Str::limit($expense->memo ?: '-', 20) }}</span></td>
                        <td class="expense-col-actions tuition-action-cell expense-actions-cell">
                            <button type="button" class="tuition-detail-button expense-detail-button"
                                data-id="{{ $expense->id }}"
                                data-expense-code="{{ $expense->expense_code }}"
                                data-school-name="{{ $expense->school_name }}"
                                data-expense-category-label="{{ $expense->expense_category_label }}"
                                data-expense-title="{{ $expense->expense_title }}"
                                data-vendor-name="{{ $expense->vendor_name }}"
                                data-scheduled-date-text="{{ $expense->scheduled_date_text }}"
                                data-paid-at-text="{{ $expense->paid_at_text }}"
                                data-payment-method-name="{{ $expense->payment_method_name }}"
                                data-amount="{{ $expense->amount }}"
                                data-tax-amount="{{ $expense->tax_amount }}"
                                data-total-amount="{{ $expense->total_amount }}"
                                data-payment-status-label="{{ $expense->payment_status_label }}"
                                data-memo="{{ $expense->memo }}"
                                data-creator-name="{{ $expense->creator_name }}"
                                data-created-at="{{ $expense->created_at_text }}"
                                data-updater-name="{{ $expense->updater_name }}"
                                data-updated-at="{{ $expense->updated_at_text }}"
                            >詳細</button>
                            <button type="button" class="tuition-edit-button expense-edit-button">編集</button>
                            <button type="button" class="tuition-save-button expense-save-button" style="display:none;">保存</button>
                            <button type="button" class="tuition-cancel-button expense-cancel-button" style="display:none;">取消</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="16">経費データがありません。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="tuition-pagination">{{ $expenses->links() }}</div>
    </section>

    <section class="tuition-chart-card shop-chart-card event-chart-card spot-chart-card refund-chart-card point-cost-chart-card expense-chart-card">
        <div class="tuition-chart-header">
            <div>
                <h3>経費グラフ</h3>
                <p>表示中データに連動して経費額推移・件数推移を確認します。</p>
            </div>
            <div class="tuition-chart-actions">
                <div class="tuition-chart-periods">
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => 'all', 'page' => null]) }}" class="{{ $chartPeriod === 'all' ? 'active' : '' }}">全期間</a>
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => '5years', 'page' => null]) }}" class="{{ $chartPeriod === '5years' ? 'active' : '' }}">直近5年</a>
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => '3years', 'page' => null]) }}" class="{{ $chartPeriod === '3years' ? 'active' : '' }}">直近3年</a>
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => '1year', 'page' => null]) }}" class="{{ $chartPeriod === '1year' ? 'active' : '' }}">直近1年</a>
                </div>
                <button type="button" id="expenseChartToggle" class="tuition-chart-collapse-button">−</button>
            </div>
        </div>
        <div class="shop-chart-grid refund-chart-grid" id="expenseChartBody">
            <div class="shop-chart-box"><h4>経費額推移</h4><div class="shop-chart-canvas-wrap"><canvas id="expenseAmountChart" data-chart='@json($expenseAmountChartData)'></canvas><div class="event-chart-empty" id="expenseAmountEmpty">該当する経費データがありません。</div></div></div>
            <div class="shop-chart-box"><h4>件数推移</h4><div class="shop-chart-canvas-wrap"><canvas id="expenseCountChart" data-chart='@json($expenseCountChartData)'></canvas><div class="event-chart-empty" id="expenseCountEmpty">該当する件数データがありません。</div></div></div>
        </div>
    </section>
</div>


<div class="tuition-column-modal" id="tuitionColumnModal" aria-hidden="true">
    <div class="tuition-column-content">
        <div class="tuition-column-header">
            <h3>表示項目設定</h3>
            <button type="button" id="tuitionColumnClose">×</button>
        </div>
        <div class="tuition-column-list">
            <label><input type="checkbox" data-column="2" checked> 教室</label>
            <label><input type="checkbox" data-column="3" checked> 経費分類</label>
            <label><input type="checkbox" data-column="4" checked> 経費名</label>
            <label><input type="checkbox" data-column="5" checked> 支払先</label>
            <label><input type="checkbox" data-column="6" checked> 支払予定日</label>
            <label><input type="checkbox" data-column="7" checked> 支払日</label>
            <label><input type="checkbox" data-column="8" checked> 支払方法</label>
            <label><input type="checkbox" data-column="9" checked> 税抜</label>
            <label><input type="checkbox" data-column="10" checked> 消費税</label>
            <label><input type="checkbox" data-column="11" checked> 税込金額</label>
            <label><input type="checkbox" data-column="12" checked> 支払状況</label>
            <label><input type="checkbox" data-column="13" checked> 登録者</label>
            <label><input type="checkbox" data-column="14" checked> 経費メモ</label>
        </div>
    </div>
</div>

<div class="expense-modal" id="expenseDetailModal" aria-hidden="true">
    <div class="expense-modal-backdrop" data-expense-modal-close></div>
    <div class="expense-modal-panel">
        <div class="expense-modal-header">
            <h3>経費詳細</h3>
            <button type="button" data-expense-modal-close>×</button>
        </div>
        <dl class="expense-detail-list" id="expenseDetailList"></dl>
    </div>
</div>

<script id="expenseInlineOptions" type="application/json">{!! json_encode([
    'schools' => $schools->map(fn ($school) => ['value' => (string) $school->id, 'label' => $school->name])->values(),
    'categories' => collect($expenseCategories)->map(fn ($category) => ['value' => $category['value'], 'label' => $category['label']])->values(),
    'paymentMethods' => $paymentMethods->map(fn ($method) => ['value' => (string) $method->id, 'label' => $method->name])->prepend(['value' => '', 'label' => '未選択'])->values(),
    'statuses' => collect($expenseStatuses)->map(fn ($status) => ['value' => $status['value'], 'label' => $status['label']])->values(),
], JSON_UNESCAPED_UNICODE) !!}</script>

@once
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/admin/expenses.js') }}"></script>
@endonce

@endsection
