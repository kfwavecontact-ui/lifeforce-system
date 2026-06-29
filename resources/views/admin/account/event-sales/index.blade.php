@extends('layouts.admin')

@section('title', '授業料・入会金売上')

@section('content')

@php
    use Illuminate\Support\Facades\Route;

    $sortLink = function (string $key) use ($sort, $direction) {
        $nextDirection = ($sort === $key && $direction === 'asc') ? 'desc' : 'asc';

        return request()->fullUrlWithQuery([
            'sort' => $key,
            'direction' => $nextDirection,
            'page' => null,
        ]);
    };

    $sortMark = function (string $key) use ($sort, $direction) {
        if ($sort !== $key) {
            return '↕';
        }

        return $direction === 'asc' ? '↑' : '↓';
    };

    $exportRouteExists = Route::has('admin.operations.classroom-accounting.event-sales.export');
    $updateRouteBase = url('/admin/operations/classroom-accounting/event-sales');
@endphp

<div class="tuition-sales-page" data-update-base="{{ $updateRouteBase }}">

    <div class="page-header">
        <div>
            <h1>イベント売上</h1>
            <p>イベント売上の取引予定日、取引日、入出金方法、割引を確認します。</p>
        </div>
    </div>

    <section class="tuition-create-card">
        <div class="tuition-create-header">
            <h3>新規ショップ売上登録</h3>
            <p>商品を選択して、ショップ売上を簡単に登録します。</p>
        </div>

        <form method="POST" action="{{ route('admin.operations.classroom-accounting.shop-sales.store') }}" id="shopSaleCreateForm">
            @csrf

            <div class="tuition-create-row tuition-create-row-main">
                <label class="tuition-student-lookup">
                    生徒
                    <input type="text" id="tuitionStudentLookupInput" placeholder="生徒コード・氏名で検索" autocomplete="off">
                    <input type="hidden" name="student_id" id="tuitionStudentId" required>
                    <div class="tuition-student-lookup-results" id="tuitionStudentLookupResults"></div>
                </label>

                <label class="shop-product-lookup">
                    商品
                    <input type="text" id="shopProductLookupInput" placeholder="商品名・商品コードで検索" autocomplete="off">
                    <input type="hidden" name="items[0][shop_product_id]" id="shopProductId" required>
                    <div class="tuition-student-lookup-results" id="shopProductLookupResults"></div>
                </label>

                <label>
                    取引予定日
                    <input type="date" name="scheduled_date" required>
                </label>

                <label>
                    取引日
                    <input type="date" name="transaction_date">
                </label>

                <label>
                    入出金方法
                    <select name="payment_method_id">
                        <option value="">未設定</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    入金状態
                    <select name="payment_status" required>
                        <option value="unpaid">未入金</option>
                        <option value="paid">入金済</option>
                        <option value="cancelled">取消</option>
                    </select>
                </label>
            </div>

            <div class="tuition-create-row tuition-create-row-sub">
                <label>
                    数量
                    <input type="number" name="items[0][quantity]" id="shopQuantityInput" min="1" value="1" required>
                </label>

                <label>
                    単価
                    <input type="text" id="shopUnitPriceDisplay" value="¥0" readonly>
                </label>

                <label>
                    割引額
                    <input type="number" name="items[0][discount_amount]" id="shopDiscountInput" min="0" value="0" required>
                </label>

                <label>
                    合計
                    <input type="text" id="shopTotalDisplay" value="¥0" readonly>
                </label>

                <label class="shop-memo-wide">
                    取引メモ
                    <input type="text" name="memo" placeholder="取引全体の補足メモ">
                </label>

                <div class="tuition-create-actions">
                    <button type="submit">登録</button>
                </div>
            </div>
        </form>
    </section>

    <section class="tuition-search-area">
        <form method="GET" action="{{ route('admin.operations.classroom-accounting.event-sales.index') }}">
            <div class="tuition-search-row">
                <label>
                    取引予定日 From
                    <input type="date" name="scheduled_from" value="{{ $filters['scheduled_from'] ?? '' }}">
                </label>

                <label>
                    取引予定日 To
                    <input type="date" name="scheduled_to" value="{{ $filters['scheduled_to'] ?? '' }}">
                </label>

                <label>
                    取引日 From
                    <input type="date" name="transaction_from" value="{{ $filters['transaction_from'] ?? '' }}">
                </label>

                <label>
                    取引日 To
                    <input type="date" name="transaction_to" value="{{ $filters['transaction_to'] ?? '' }}">
                </label>
            </div>

            <div class="tuition-search-row">
                <label class="tuition-search-school">
                    教室
                    <select name="school_id">
                        <option value="">すべて</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected(($filters['school_id'] ?? '') == $school->id)>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    会計区分
                    <select name="account_category_id">
                        <option value="">すべて</option>
                        @foreach ($accountCategories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['account_category_id'] ?? '') == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    コース名
                    <select name="course_name">
                        <option value="">すべて</option>
                        @foreach ($courseNames as $courseName)
                            <option value="{{ $courseName }}" @selected(($filters['course_name'] ?? '') === $courseName)>
                                {{ $courseName }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    通塾種別
                    <select name="attendance_type">
                        <option value="">すべて</option>
                        @foreach ($attendanceTypes as $attendanceType)
                            <option value="{{ $attendanceType }}" @selected(($filters['attendance_type'] ?? '') === $attendanceType)>
                                {{ $attendanceType }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    入出金方法
                    <select name="payment_method_id">
                        <option value="">すべて</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->id }}" @selected(($filters['payment_method_id'] ?? '') == $method->id)>
                                {{ $method->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="tuition-search-row tuition-search-row-last">
                <label>
                    入金状態
                    <select name="payment_status">
                        <option value="">すべて</option>
                        <option value="paid" @selected(($filters['payment_status'] ?? '') === 'paid')>入金済</option>
                        <option value="unpaid" @selected(($filters['payment_status'] ?? '') === 'unpaid')>未入金</option>
                        <option value="cancelled" @selected(($filters['payment_status'] ?? '') === 'cancelled')>取消</option>
                    </select>
                </label>

                <label>
                    割引種別
                    <select name="discount_type_id">
                        <option value="">すべて</option>
                        @foreach ($discounts as $discount)
                            <option value="{{ $discount->id }}" @selected(($filters['discount_type_id'] ?? '') == $discount->id)>
                                {{ $discount->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="tuition-search-keyword">
                    キーワード
                    <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="生徒名・生徒コード・取引名・コース名・割引メモ">
                </label>

                <div class="tuition-search-actions">
                    <button type="submit" class="tuition-search-button">検索</button>

                    <a href="{{ route('admin.operations.classroom-accounting.event-sales.index') }}" class="tuition-clear-button">
                        クリア
                    </a>

                    @if ($exportRouteExists)
                        <a href="{{ route('admin.operations.classroom-accounting.event-sales.export', request()->query()) }}" class="tuition-export-button">
                            CSV出力
                        </a>
                    @endif

                    <button type="button" class="tuition-column-setting-button" id="tuitionColumnSettingButton">
                        <i class="fas fa-gear"></i>
                        表示項目
                    </button>
                </div>
            </div>
        </form>
    </section>

    <script>
        window.tuitionSalesChartData = @json($tuitionSalesChartData);
        window.courseCompositionData = @json($courseCompositionData);
        window.tuitionPaymentMethods = @json($paymentMethods->map(fn($method) => [
            'id' => $method->id,
            'name' => $method->name,
        ])->values());
    </script>

    <section class="tuition-table-card">
        <div class="tuition-table-scroll">
            <table class="tuition-sales-table">
                <thead>
                    <tr>
                        <th class="tuition-check-column">
                            <input type="checkbox" id="tuitionCheckAll">
                        </th>

                        <th>
                            <a href="{{ $sortLink('id') }}" class="tuition-sort-link">
                                ID <span>{{ $sortMark('id') }}</span>
                            </a>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <a href="{{ $sortLink('school') }}" class="tuition-sort-link">
                                    教室 <span>{{ $sortMark('school') }}</span>
                                </a>
                                <button type="button" class="tuition-filter-button" data-filter="school">▼</button>
                            </div>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <a href="{{ $sortLink('student') }}" class="tuition-sort-link">
                                    生徒 <span>{{ $sortMark('student') }}</span>
                                </a>
                                <button type="button" class="tuition-filter-button" data-filter="student">▼</button>
                            </div>
                        </th>

                        <th>
                            <a href="{{ $sortLink('student_code') }}" class="tuition-sort-link">
                                生徒コード <span>{{ $sortMark('student_code') }}</span>
                            </a>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <a href="{{ $sortLink('course_name') }}" class="tuition-sort-link">
                                    コース名 <span>{{ $sortMark('course_name') }}</span>
                                </a>
                                <button type="button" class="tuition-filter-button" data-filter="course_name">▼</button>
                            </div>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <a href="{{ $sortLink('attendance_type') }}" class="tuition-sort-link">
                                    通塾種別 <span>{{ $sortMark('attendance_type') }}</span>
                                </a>
                                <button type="button" class="tuition-filter-button" data-filter="attendance_type">▼</button>
                            </div>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <a href="{{ $sortLink('account_category') }}" class="tuition-sort-link">
                                    会計区分 <span>{{ $sortMark('account_category') }}</span>
                                </a>
                                <button type="button" class="tuition-filter-button" data-filter="account_category">▼</button>
                            </div>
                        </th>

                        <th>
                            <a href="{{ $sortLink('scheduled_date') }}" class="tuition-sort-link">
                                取引予定日 <span>{{ $sortMark('scheduled_date') }}</span>
                            </a>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <a href="{{ $sortLink('payment_method') }}" class="tuition-sort-link">
                                    入出金方法 <span>{{ $sortMark('payment_method') }}</span>
                                </a>
                                <button type="button" class="tuition-filter-button" data-filter="payment_method">▼</button>
                            </div>
                        </th>

                        <th>
                            <a href="{{ $sortLink('transaction_date') }}" class="tuition-sort-link">
                                取引日 <span>{{ $sortMark('transaction_date') }}</span>
                            </a>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <span>割引種別</span>
                                <button type="button" class="tuition-filter-button" data-filter="discount_type">▼</button>
                            </div>
                        </th>

                        <th>
                            <a href="{{ $sortLink('before_discount_amount') }}" class="tuition-sort-link">
                                割引前金額 <span>{{ $sortMark('before_discount_amount') }}</span>
                            </a>
                        </th>

                        <th>
                            <a href="{{ $sortLink('discount_amount') }}" class="tuition-sort-link">
                                割引額 <span>{{ $sortMark('discount_amount') }}</span>
                            </a>
                        </th>

                        <th>
                            <a href="{{ $sortLink('amount') }}" class="tuition-sort-link">
                                取引額(税込) <span>{{ $sortMark('amount') }}</span>
                            </a>
                        </th>

                        <th>
                            <div class="tuition-filter-header">
                                <a href="{{ $sortLink('payment_status') }}" class="tuition-sort-link">
                                    入金状態 <span>{{ $sortMark('payment_status') }}</span>
                                </a>
                                <button type="button" class="tuition-filter-button" data-filter="payment_status">▼</button>
                            </div>
                        </th>

                        <th>取引メモ</th>
                        <th>割引メモ</th>

                        <th>操作</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($sales as $sale)
                        <tr data-row-id="{{ $sale->id }}">
                            <td class="tuition-check-column">
                                <input type="checkbox" class="tuition-row-check" value="{{ $sale->id }}">
                            </td>

                            <td>{{ $sale->id }}</td>
                            <td>{{ $sale->school_name }}</td>
                            <td>{{ $sale->student_name }}</td>
                            <td>{{ $sale->student_code }}</td>
                            <td>{{ $sale->course_name }}</td>
                            <td>{{ $sale->attendance_type }}</td>
                            <td>{{ $sale->account_category_name }}</td>

                            <td data-edit-field="scheduled_date" data-value="{{ $sale->scheduled_date }}">
                                <span class="display-value">
                                    {{ $sale->scheduled_date ? \Carbon\Carbon::parse($sale->scheduled_date)->format('Y/m/d') : '-' }}
                                </span>
                            </td>

                            <td data-edit-field="payment_method_id" data-value="{{ $sale->payment_method_id }}">
                                <span class="display-value">{{ $sale->payment_method_name }}</span>
                            </td>

                            <td data-edit-field="transaction_date" data-value="{{ $sale->transaction_date ? \Carbon\Carbon::parse($sale->transaction_date)->format('Y-m-d') : '' }}">
                                <span class="display-value">
                                    {{ $sale->transaction_date ? \Carbon\Carbon::parse($sale->transaction_date)->format('Y/m/d') : '-' }}
                                </span>
                            </td>

                            <td class="tuition-discount-cell">{{ $sale->discount_type_name }}</td>

                            <td class="tuition-amount-cell" data-edit-field="before_discount_amount" data-value="{{ $sale->before_discount_amount }}">
                                <span class="display-value">¥{{ number_format($sale->before_discount_amount) }}</span>
                            </td>

                            <td class="tuition-amount-cell tuition-discount-amount" data-edit-field="discount_amount" data-value="{{ $sale->discount_amount }}">
                                <span class="display-value">¥{{ number_format($sale->discount_amount) }}</span>
                            </td>

                            <td class="tuition-amount-cell tuition-final-amount">¥{{ number_format($sale->amount) }}</td>

                            <td data-edit-field="payment_status" data-value="{{ $sale->payment_status }}">
                                <span class="display-value tuition-status-badge {{ $sale->payment_status }}">
                                    {{ $sale->payment_status_label }}
                                </span>
                            </td>

                            <td data-edit-field="memo" data-value="{{ $sale->transaction_note }}">
                                <span class="display-value">{{ $sale->transaction_note }}</span>
                            </td>

                            <td data-edit-field="discount_note" data-value="{{ $sale->discount_note }}">
                                <span class="display-value">{{ $sale->discount_note }}</span>
                            </td>

                            <td class="tuition-action-cell">
                                <button
                                    type="button"
                                    class="tuition-detail-button"
                                    data-id="{{ $sale->id }}"
                                    data-school="{{ $sale->school_name }}"
                                    data-student="{{ $sale->student_name }}"
                                    data-student-code="{{ $sale->student_code }}"
                                    data-course="{{ $sale->course_name }}"
                                    data-attendance="{{ $sale->attendance_type }}"
                                    data-category="{{ $sale->account_category_name }}"
                                    data-scheduled-date="{{ $sale->scheduled_date ? \Carbon\Carbon::parse($sale->scheduled_date)->format('Y/m/d') : '-' }}"
                                    data-payment-method="{{ $sale->payment_method_name }}"
                                    data-transaction-date="{{ $sale->transaction_date ? \Carbon\Carbon::parse($sale->transaction_date)->format('Y/m/d') : '-' }}"
                                    data-discount-type="{{ $sale->discount_type_name }}"
                                    data-before-discount="{{ number_format($sale->before_discount_amount) }}"
                                    data-discount-amount="{{ number_format($sale->discount_amount) }}"
                                    data-amount="{{ number_format($sale->amount) }}"
                                    data-payment-status="{{ $sale->payment_status_label }}"
                                    data-invoice-id="{{ $sale->invoice_id }}"
                                    data-invoice-item-id="{{ $sale->id }}"
                                    data-account-transaction-id="{{ $sale->account_transaction_id }}"
                                    data-created-by="{{ $sale->created_by_name }}"
                                    data-updated-by="{{ $sale->updated_by_name }}"
                                    data-created-at="{{ $sale->created_at ? \Carbon\Carbon::parse($sale->created_at)->format('Y/m/d H:i') : '-' }}"
                                    data-updated-at="{{ $sale->updated_at ? \Carbon\Carbon::parse($sale->updated_at)->format('Y/m/d H:i') : '-' }}"
                                    data-transaction-note="{{ $sale->transaction_note }}"
                                    data-discount-note="{{ $sale->discount_note }}"
                                >
                                    詳細
                                </button>

                                <button type="button" class="tuition-edit-button">編集</button>
                                <button type="button" class="tuition-save-button" style="display:none;">保存</button>
                                <button type="button" class="tuition-cancel-button" style="display:none;">取消</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="18">授業料・入会金売上データがありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="tuition-pagination">
            {{ $sales->links() }}
        </div>
    </section>

    <section class="tuition-chart-card">
        <div class="tuition-chart-header">
            <div>
                <h3>授業料売上推移</h3>
                <p>コース別の授業料売上と人数推移を確認できます。</p>
            </div>

            <div class="tuition-chart-actions">
                <div class="tuition-chart-periods">
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => 'all']) }}" class="{{ $chartPeriod === 'all' ? 'active' : '' }}">全期間</a>
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => '5years']) }}" class="{{ $chartPeriod === '5years' ? 'active' : '' }}">直近5年</a>
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => '3years']) }}" class="{{ $chartPeriod === '3years' ? 'active' : '' }}">直近3年</a>
                    <a href="{{ request()->fullUrlWithQuery(['chart_period' => '1year']) }}" class="{{ $chartPeriod === '1year' ? 'active' : '' }}">直近1年</a>
                </div>

                <button type="button" id="tuitionSalesChartToggle" class="tuition-chart-collapse-button" title="グラフを折りたたむ">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>

        <div class="tuition-chart-wrapper" id="tuitionSalesChartBody">
            <canvas id="tuitionSalesChart"></canvas>
        </div>
    </section>

</div>

<div class="tuition-detail-modal" id="tuitionDetailModal">
    <div class="tuition-detail-content">
        <div class="tuition-detail-header">
            <h3>売上詳細</h3>
            <button type="button" id="tuitionDetailClose">×</button>
        </div>

        <div id="tuitionDetailBody"></div>
    </div>
</div>

<div class="tuition-column-modal" id="tuitionColumnModal">
    <div class="tuition-column-content">
        <div class="tuition-column-header">
            <h3>表示項目設定</h3>
            <button type="button" id="tuitionColumnClose">×</button>
        </div>

        <div class="tuition-column-list">
            <label><input type="checkbox" data-column="2" checked> 教室</label>
            <label><input type="checkbox" data-column="3" checked> 生徒</label>
            <label><input type="checkbox" data-column="4" checked> 生徒コード</label>
            <label><input type="checkbox" data-column="5" checked> コース名</label>
            <label><input type="checkbox" data-column="6" checked> 通塾種別</label>
            <label><input type="checkbox" data-column="7" checked> 会計区分</label>
            <label><input type="checkbox" data-column="10" checked> 入出金方法</label>
            <label><input type="checkbox" data-column="12" checked> 割引種別</label>
            <label><input type="checkbox" data-column="13" checked> 割引前金額</label>
            <label><input type="checkbox" data-column="14" checked> 割引額</label>
            <label><input type="checkbox" data-column="16">取引メモ</label>
            <label><input type="checkbox" data-column="17">割引メモ</label>
        </div>
    </div>
</div>

@endsection
