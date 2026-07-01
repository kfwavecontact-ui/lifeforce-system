@extends('layouts.admin')

@section('title', '返金')

@section('content')

@once
    <link rel="stylesheet" href="{{ asset('css/admin/tuition-enrollment-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/shop-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/event-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/spot-sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/refunds.css') }}">
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
    $yoyText = function ($rate, string $suffix = '%') {
        if ($rate === null) return '前年同月比 -';
        $prefix = $rate > 0 ? '+' : '';
        return '前年同月比 ' . $prefix . $rate . $suffix;
    };
    $exportRouteExists = Route::has('admin.operations.classroom-accounting.refunds.export');
    $updateRouteBase = url('/admin/operations/classroom-accounting/refunds');
@endphp

<div class="tuition-sales-page shop-sales-page event-sales-page spot-sales-page refund-page" data-update-base="{{ $updateRouteBase }}" data-target-search-url="{{ route('admin.operations.classroom-accounting.refunds.targets.search') }}">
    <div class="page-header">
        <div>
            <h1>返金</h1>
            <p>授業料・入会金、ショップ、イベント、スポット売上に対する返金を確認します。</p>
        </div>
    </div>

    @if (session('success'))
        <div class="tuition-alert-success">{{ session('success') }}</div>
        <div id="refundToastMessage" data-message="{{ session('success') }}"></div>
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

    <section class="shop-sale-create-card event-sale-create-card spot-sale-create-card refund-create-card shop-sale-create-card-compact">
        <div class="shop-sale-create-header refund-create-header">
            <div>
                <h3>新規返金登録</h3>
                <p>返金元区分を選択し、入金済の返金対象を検索して登録します。</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.operations.classroom-accounting.refunds.store') }}" id="refundCreateForm" class="event-sale-create-form refund-create-form refund-create-form-compact">
            @csrf
            <input type="hidden" name="refund_source_id" id="refundSourceId">
            <input type="hidden" name="student_id" id="refundStudentId">

            <div class="refund-create-two-line-grid">
                <label class="shop-sale-field refund-source-field">
                    <span>返金元区分</span>
                    <select name="refund_source_type" id="refundSourceType" required>
                        @foreach ($refundSourceTypes as $type)
                            <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="shop-sale-field refund-lookup-field" id="refundTargetField">
                    <span>返金対象</span>
                    <div class="refund-lookup-row">
                        <input type="text" id="refundTargetLookupInput" placeholder="レコードID・生徒ID・生徒名で検索" autocomplete="off">
                        <button type="button" class="tuition-search-button refund-lookup-search-button" id="refundTargetSearchButton">検索</button>
                        <button type="button" class="tuition-clear-button refund-target-clear-button" id="refundTargetClearButton">×クリア</button>
                    </div>
                    <div class="refund-lookup-results" id="refundTargetResults"></div>
                </div>

                <label class="shop-sale-field" id="refundStudentField" style="display:none;">
                    <span>生徒</span>
                    <input type="text" id="refundStudentLookupInput" placeholder="生徒コード・氏名で検索" autocomplete="off">
                    <div class="event-student-search-results" id="refundStudentResults"></div>
                </label>

                <label class="shop-sale-field">
                    <span>返金額</span>
                    <input type="number" name="refund_amount" id="refundAmount" min="1" required>
                    <small id="refundAmountHelp">返金可能額以内で入力してください。</small>
                </label>

                <label class="shop-sale-field">
                    <span>状態</span>
                    <select name="status" id="refundStatus">
                        @foreach ($refundStatuses as $status)
                            <option value="{{ $status['value'] }}">{{ $status['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="shop-sale-field">
                    <span>返金予定日</span>
                    <input type="date" name="scheduled_date" id="refundScheduledDate" required>
                </label>

                <label class="shop-sale-field">
                    <span>返金日</span>
                    <input type="date" name="refunded_at" id="refundCompletedDate">
                </label>

                <label class="shop-sale-field">
                    <span>返金方法</span>
                    <select name="refund_method_id">
                        <option value="">未設定</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="shop-sale-field refund-reason-field">
                    <span>返金理由</span>
                    <input type="text" name="refund_reason" id="refundReason" placeholder="例：保護者都合によるキャンセル">
                    <small id="refundReasonHelp">未返金・返金済の場合は必須です。</small>
                </label>

                <label class="shop-sale-field refund-memo-field">
                    <span>メモ</span>
                    <input type="text" name="memo" placeholder="例：電話連絡済">
                </label>
            </div>

            <div class="refund-create-bottom-row">
                <div class="refund-total-box refund-total-box-inline">
                    <div><span>返金可能額</span><strong id="refundMaxAmountText">¥0</strong></div>
                    <div><span>登録返金額</span><strong id="refundAmountText">¥0</strong></div>
                </div>
                <button type="submit" class="tuition-search-button refund-submit-button" id="refundSubmitButton">返金を登録</button>
            </div>
        </form>
    </section>

    <section class="tuition-filter-card shop-filter-card event-filter-card spot-filter-card refund-filter-card">
        <form method="GET" action="{{ route('admin.operations.classroom-accounting.refunds.index') }}" class="spot-sale-search-form refund-search-form">
            <div class="spot-sale-search-grid refund-search-grid">
                <label>返金予定日 From<input type="date" name="scheduled_from" value="{{ $filters['scheduled_from'] ?? '' }}"></label>
                <label>返金予定日 To<input type="date" name="scheduled_to" value="{{ $filters['scheduled_to'] ?? '' }}"></label>
                <label>返金日 From<input type="date" name="refunded_from" value="{{ $filters['refunded_from'] ?? '' }}"></label>
                <label>返金日 To<input type="date" name="refunded_to" value="{{ $filters['refunded_to'] ?? '' }}"></label>
                <label>教室<select name="school_id"><option value="">すべて</option>@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(($filters['school_id'] ?? '') == $school->id)>{{ $school->name }}</option>@endforeach</select></label>
                <label>返金元区分<select name="refund_source_type"><option value="">すべて</option>@foreach ($refundSourceTypes as $type)<option value="{{ $type['value'] }}" @selected(($filters['refund_source_type'] ?? '') === $type['value'])>{{ $type['label'] }}</option>@endforeach</select></label>
                <label>返金方法<select name="refund_method_id"><option value="">すべて</option>@foreach ($paymentMethods as $method)<option value="{{ $method->id }}" @selected(($filters['refund_method_id'] ?? '') == $method->id)>{{ $method->name }}</option>@endforeach</select></label>
                <label>状態<select name="status"><option value="">すべて</option>@foreach ($refundStatuses as $status)<option value="{{ $status['value'] }}" @selected(($filters['status'] ?? '') === $status['value'])>{{ $status['label'] }}</option>@endforeach</select></label>
                <label class="refund-keyword-field">キーワード<input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="返金番号・生徒・理由"></label>
            </div>
            <div class="tuition-search-buttons event-sale-search-buttons refund-search-actions">
                <button type="submit" class="tuition-search-button">検索</button>
                <a href="{{ route('admin.operations.classroom-accounting.refunds.index') }}" class="tuition-clear-button">クリア</a>
                @if ($exportRouteExists)
                    <a href="{{ route('admin.operations.classroom-accounting.refunds.export', request()->query()) }}" class="tuition-export-button">CSV出力</a>
                @endif
                <button type="button" id="tuitionColumnButton" class="tuition-column-setting-button"><i class="fas fa-gear"></i> 表示項目</button>
            </div>
        </form>
    </section>

    <section class="tuition-table-card shop-table-card event-table-card spot-table-card refund-table-card">
        <div class="tuition-table-header"><p>表示中 {{ number_format($refunds->total()) }}件 / 全 {{ number_format($totalRefundCount) }}件</p></div>
        <div class="tuition-table-scroll">
            <table class="tuition-table event-sales-table spot-sales-table refund-table" id="refundTable">
                <thead><tr>
                    <th class="tuition-check-column"><input type="checkbox" id="tuitionCheckAll"></th>
                    <th><a href="{{ $sortLink('id') }}" class="tuition-sort-link">ID <span>{{ $sortMark('id') }}</span></a></th>
                    <th><a href="{{ $sortLink('refund_code') }}" class="tuition-sort-link">返金番号 <span>{{ $sortMark('refund_code') }}</span></a></th>
                    <th><a href="{{ $sortLink('school') }}" class="tuition-sort-link">教室 <span>{{ $sortMark('school') }}</span></a><button type="button" class="refund-column-filter-button" data-filter-col="school">▼</button></th>
                    <th><a href="{{ $sortLink('student') }}" class="tuition-sort-link">生徒 <span>{{ $sortMark('student') }}</span></a><button type="button" class="refund-column-filter-button" data-filter-col="student">▼</button></th>
                    <th>生徒コード</th>
                    <th><a href="{{ $sortLink('source_type') }}" class="tuition-sort-link">返金元区分 <span>{{ $sortMark('source_type') }}</span></a><button type="button" class="refund-column-filter-button" data-filter-col="source">▼</button></th>
                    <th>返金対象</th>
                    <th><a href="{{ $sortLink('scheduled_date') }}" class="tuition-sort-link">返金予定日 <span>{{ $sortMark('scheduled_date') }}</span></a></th>
                    <th><a href="{{ $sortLink('refund_method') }}" class="tuition-sort-link">返金方法 <span>{{ $sortMark('refund_method') }}</span></a><button type="button" class="refund-column-filter-button" data-filter-col="method">▼</button></th>
                    <th><a href="{{ $sortLink('refunded_at') }}" class="tuition-sort-link">返金日 <span>{{ $sortMark('refunded_at') }}</span></a></th>
                    <th><a href="{{ $sortLink('amount') }}" class="tuition-sort-link">返金額 <span>{{ $sortMark('amount') }}</span></a></th>
                    <th><a href="{{ $sortLink('status') }}" class="tuition-sort-link">状態 <span>{{ $sortMark('status') }}</span></a><button type="button" class="refund-column-filter-button" data-filter-col="status">▼</button></th>
                    <th>返金理由</th><th>メモ</th><th>操作</th>
                </tr></thead>
                <tbody>
                @forelse ($refunds as $refund)
                    <tr data-row-id="{{ $refund->id }}" data-chart-date="{{ $refund->refunded_at ?: $refund->scheduled_date }}" data-chart-amount="{{ $refund->refund_amount }}">
                        <td class="tuition-check-column"><input type="checkbox" class="tuition-row-check" value="{{ $refund->id }}"></td>
                        <td>{{ $refund->id }}</td><td>{{ $refund->refund_code }}</td><td data-filter-school="{{ $refund->school_name }}">{{ $refund->school_name }}</td><td data-filter-student="{{ $refund->student_name }}">{{ $refund->student_name }}</td><td>{{ $refund->student_code }}</td>
                        <td data-filter-source="{{ $refund->refund_source_type_label }}">{{ $refund->refund_source_type_label }}</td>
                        <td class="lf-tooltip event-memo-cell refund-target-cell" data-tooltip="{{ $refund->refund_target_label }}" title="{{ $refund->refund_target_label }}">{{ \Illuminate\Support\Str::limit($refund->refund_target_label, 36) }}</td>
                        <td data-edit-field="scheduled_date" data-value="{{ $refund->scheduled_date }}"><span class="display-value">{{ $refund->scheduled_date ? \Carbon\Carbon::parse($refund->scheduled_date)->format('Y/m/d') : '-' }}</span></td>
                        <td data-filter-method="{{ $refund->refund_method_name }}" data-edit-field="refund_method_id" data-value="{{ $refund->refund_method_id }}"><span class="display-value">{{ $refund->refund_method_name }}</span></td>
                        <td data-edit-field="refunded_at" data-value="{{ $refund->refunded_at }}"><span class="display-value">{{ $refund->refunded_at ? \Carbon\Carbon::parse($refund->refunded_at)->format('Y/m/d') : '-' }}</span></td>
                        <td class="tuition-amount-cell" data-edit-field="refund_amount" data-value="{{ $refund->refund_amount }}"><span class="display-value">¥{{ number_format($refund->refund_amount) }}</span></td>
                        <td data-filter-status="{{ $refund->status_label }}" data-edit-field="status" data-value="{{ $refund->status }}"><span class="display-value tuition-status-badge {{ $refund->status_class }}">{{ $refund->status_label }}</span></td>
                        <td data-edit-field="refund_reason" data-value="{{ $refund->refund_reason === '-' ? '' : $refund->refund_reason }}" class="lf-tooltip event-memo-cell" data-tooltip="{{ $refund->refund_reason }}" title="{{ $refund->refund_reason }}"><span class="display-value">{{ \Illuminate\Support\Str::limit($refund->refund_reason, 20) }}</span></td>
                        <td data-edit-field="memo" data-value="{{ $refund->memo === '-' ? '' : $refund->memo }}" class="lf-tooltip event-memo-cell" data-tooltip="{{ $refund->memo }}" title="{{ $refund->memo }}"><span class="display-value">{{ \Illuminate\Support\Str::limit($refund->memo, 20) }}</span></td>
                        <td class="tuition-action-cell">
                            <button type="button" class="tuition-detail-button"
                                data-id="{{ $refund->id }}" data-refund-code="{{ $refund->refund_code }}" data-school="{{ $refund->school_name }}" data-student="{{ $refund->student_name }}" data-student-code="{{ $refund->student_code }}" data-source-type="{{ $refund->refund_source_type_label }}" data-target="{{ $refund->refund_target_label }}" data-scheduled-date="{{ $refund->scheduled_date ? \Carbon\Carbon::parse($refund->scheduled_date)->format('Y/m/d') : '-' }}" data-refund-method="{{ $refund->refund_method_name }}" data-refunded-at="{{ $refund->refunded_at ? \Carbon\Carbon::parse($refund->refunded_at)->format('Y/m/d') : '-' }}" data-amount="{{ number_format($refund->refund_amount) }}" data-status="{{ $refund->status_label }}" data-reason="{{ $refund->refund_reason }}" data-memo="{{ $refund->memo }}" data-created-by="{{ $refund->created_by_name ?? '-' }}" data-updated-by="{{ $refund->updated_by_name ?? '-' }}">詳細</button>
                            <button type="button" class="tuition-edit-button">編集</button>
                            <button type="button" class="tuition-save-button" style="display:none;">保存</button>
                            <button type="button" class="tuition-cancel-button" style="display:none;">取消</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="16">返金データがありません。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="tuition-pagination">{{ $refunds->links() }}</div>
    </section>

    <section class="tuition-chart-card shop-chart-card event-chart-card spot-chart-card refund-chart-card">
        <div class="tuition-chart-header"><div><h3>返金グラフ</h3><p>表示中データに連動して返金推移・件数推移・返金元区分を確認します。</p></div><div class="tuition-chart-actions"><div class="tuition-chart-periods"><a href="{{ request()->fullUrlWithQuery(['chart_period' => 'all']) }}" class="{{ $chartPeriod === 'all' ? 'active' : '' }}">全期間</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '5years']) }}" class="{{ $chartPeriod === '5years' ? 'active' : '' }}">直近5年</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '3years']) }}" class="{{ $chartPeriod === '3years' ? 'active' : '' }}">直近3年</a><a href="{{ request()->fullUrlWithQuery(['chart_period' => '1year']) }}" class="{{ $chartPeriod === '1year' ? 'active' : '' }}">直近1年</a></div><button type="button" id="eventChartToggle" class="tuition-chart-collapse-button">−</button></div></div>
        <div class="shop-chart-grid refund-chart-grid" id="eventChartBody">
            <div class="shop-chart-box"><h4>返金額推移</h4><div class="shop-chart-canvas-wrap"><canvas id="refundAmountChart"></canvas><div class="event-chart-empty" id="refundAmountEmpty">該当する返金データがありません。</div></div></div>
            <div class="shop-chart-box"><h4>件数推移</h4><div class="shop-chart-canvas-wrap"><canvas id="refundCountChart"></canvas><div class="event-chart-empty" id="refundCountEmpty">該当する件数データがありません。</div></div></div>
            <div class="shop-chart-box"><h4>返金元区分</h4><div class="shop-chart-canvas-wrap"><canvas id="refundSourceChart"></canvas><div class="event-chart-empty" id="refundSourceEmpty">該当する内訳データがありません。</div></div></div>
            <div class="shop-chart-box refund-summary-chart-box">
                <h4>返金サマリー</h4>
                <div class="refund-summary-chart-grid">
                    <div>
                        <span>返金額</span>
                        <strong>今月 ¥{{ number_format($summary['this_month_amount'] ?? 0) }}</strong>
                        <em>先月 ¥{{ number_format($summary['last_month_amount'] ?? 0) }}</em>
                        <em>{{ $yoyText($summary['amount_yoy_rate'] ?? null) }}</em>
                    </div>
                    <div>
                        <span>未返金額</span>
                        <strong>今月 ¥{{ number_format($summary['this_month_pending_amount'] ?? 0) }}</strong>
                        <em>先月 ¥{{ number_format($summary['last_month_pending_amount'] ?? 0) }}</em>
                        <em>{{ $yoyText($summary['pending_amount_yoy_rate'] ?? null) }}</em>
                    </div>
                    <div>
                        <span>返金件数</span>
                        <strong>今月 {{ number_format($summary['this_month_count'] ?? 0) }}件</strong>
                        <em>先月 {{ number_format($summary['last_month_count'] ?? 0) }}件</em>
                        <em>{{ $yoyText($summary['count_yoy_rate'] ?? null) }}</em>
                    </div>
                    <div>
                        <span>返金率</span>
                        <strong>今月 {{ $summary['this_month_completion_rate'] ?? 0 }}%</strong>
                        <em>先月 {{ $summary['last_month_completion_rate'] ?? 0 }}%</em>
                        <em>{{ $yoyText($summary['completion_rate_yoy_rate'] ?? null, 'pt') }}</em>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="tuition-detail-modal" id="tuitionDetailModal"><div class="tuition-detail-content refund-detail-content"><div class="tuition-detail-header"><h3>返金詳細</h3><button type="button" id="tuitionDetailClose">×</button></div><div id="tuitionDetailBody"></div></div></div>

<div class="refund-lookup-modal" id="refundLookupModal">
    <div class="refund-lookup-modal-content">
        <div class="refund-lookup-modal-header">
            <div>
                <h3>返金対象検索結果</h3>
                <p id="refundLookupCount">検索結果を選択してください。</p>
            </div>
            <button type="button" id="refundLookupClose">×</button>
        </div>
        <div class="refund-lookup-table-wrap">
            <table class="refund-lookup-table">
                <thead>
                    <tr>
                        <th>番号</th>
                        <th>生徒</th>
                        <th>金額</th>
                        <th>取引日</th>
                        <th>返金可能額</th>
                        <th>選択</th>
                    </tr>
                </thead>
                <tbody id="refundLookupTableBody"></tbody>
            </table>
        </div>
    </div>
</div>


<div class="tuition-column-modal" id="tuitionColumnModal"><div class="tuition-column-content"><div class="tuition-column-header"><h3>表示項目設定</h3><button type="button" id="tuitionColumnClose">×</button></div><div class="tuition-column-list"><label><input type="checkbox" data-column="2" checked> 返金番号</label><label><input type="checkbox" data-column="3" checked> 教室</label><label><input type="checkbox" data-column="4" checked> 生徒</label><label><input type="checkbox" data-column="5" checked> 生徒コード</label><label><input type="checkbox" data-column="6" checked> 返金元区分</label><label><input type="checkbox" data-column="7" checked> 返金対象</label><label><input type="checkbox" data-column="9" checked> 返金方法</label><label><input type="checkbox" data-column="13"> 返金理由</label><label><input type="checkbox" data-column="14"> メモ</label></div></div></div>

<script>
    window.refundAmountChartData = @json($refundAmountChartData);
    window.refundCountChartData = @json($refundCountChartData);
    window.refundSourceChartData = @json($refundSourceChartData);
    window.refundPaymentMethods = @json($paymentMethods->map(fn($m) => ['id' => $m->id, 'name' => $m->name])->values());
    window.refundStatuses = @json($refundStatuses);
</script>

@once
    <script src="{{ asset('js/admin/refunds.js') }}" defer></script>
@endonce

@endsection
