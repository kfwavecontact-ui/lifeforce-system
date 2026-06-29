@extends('layouts.admin')

@section('title', '会計台帳')

@section('content')

@php
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
@endphp

<div class="account-ledger-page">

    <div class="page-header">
        <div>
            <h1>会計台帳</h1>
            <p>教室で発生した収益・費用・割引・元データを一覧で確認します。</p>
        </div>
    </div>

    
    <section class="account-search-card">
        <form method="GET" action="{{ route('admin.operations.classroom-accounting.transactions.index') }}">

            <div class="account-search-row">
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

                <label class="account-search-school">
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
            </div>

            <div class="account-search-row account-search-row-second">
                <label>
                    収支区分
                    <select name="transaction_type">
                        <option value="">すべて</option>
                        <option value="収益" @selected(($filters['transaction_type'] ?? '') === '収益')>収益</option>
                        <option value="費用" @selected(($filters['transaction_type'] ?? '') === '費用')>費用</option>
                    </select>
                </label>

                <label>
                    会計カテゴリ
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

                <label>
                    状態
                    <select name="status">
                        <option value="">すべて</option>
                        <option value="planned" @selected(($filters['status'] ?? '') === 'planned')>取引中</option>
                        <option value="confirmed" @selected(($filters['status'] ?? '') === 'confirmed')>取引完了</option>
                        <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>取消</option>
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

                <label class="account-search-keyword">
                    キーワード
                    <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="取引名・生徒名・割引メモ・元データ">
                </label>

                <div class="account-search-actions">
                    <button type="submit" class="account-search-button">検索</button>

                    <a href="{{ route('admin.operations.classroom-accounting.transactions.index') }}" class="account-clear-button">
                        クリア
                    </a>

                    <a href="{{ route('admin.operations.classroom-accounting.transactions.export', request()->query()) }}" class="account-export-button">
                        CSV出力
                    </a>

                    <button type="button" class="account-column-setting-button" id="accountColumnSettingButton">
                        <i class="fas fa-gear"></i>
                        表示項目
                    </button>
                </div>
            </div>

        </form>
    </section>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('monthlyChart');
        const chartData = @json($monthlyChartData);

        if (!canvas || !Array.isArray(chartData)) {
            return;
        }

        new Chart(canvas, {
            data: {
                labels: chartData.map(item => item.label),
                datasets: [
                    {
                        type: 'bar',
                        label: '収益',
                        data: chartData.map(item => Number(item.income || 0)),
                        backgroundColor: 'rgba(37, 99, 235, 0.45)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        borderWidth: 1,
                    },
                    {
                        type: 'bar',
                        label: '費用',
                        data: chartData.map(item => Number(item.expense || 0)),
                        backgroundColor: 'rgba(220, 38, 38, 0.35)',
                        borderColor: 'rgba(220, 38, 38, 1)',
                        borderWidth: 1,
                    },
                    {
                        type: 'line',
                        label: '累計利益',
                        data: chartData.map(item => Number(item.cumulative_profit || 0)),
                        borderColor: 'rgba(245, 158, 11, 1)',
                        backgroundColor: 'rgba(245, 158, 11, 0.15)',
                        borderWidth: 3,
                        tension: 0.35,
                        pointRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            generateLabels(chart) {
                                const labels = Chart.defaults.plugins.legend.labels.generateLabels(chart);

                                labels.forEach(label => {
                                    if (label.text === '累計利益') {
                                        label.pointStyle = 'line';
                                    }
                                });

                                return labels;
                            },
                        },
                    },
                },
            },
        });

        const chartToggle = document.getElementById('accountChartToggle');
        const chartBody = document.getElementById('accountChartBody');

        if (chartToggle && chartBody) {

            chartToggle.addEventListener('click', () => {

                chartBody.classList.toggle('is-collapsed');

                const collapsed = chartBody.classList.contains('is-collapsed');

                chartToggle.innerHTML = collapsed
                    ? '<i class="fas fa-plus"></i>'
                    : '<i class="fas fa-minus"></i>';

                chartToggle.title = collapsed
                    ? 'グラフを表示'
                    : 'グラフを折りたたむ';

            });

        }



    });
    </script>

    <section class="account-table-card">
        <div class="account-table-scroll">
            <table class="account-ledger-table">
                <thead>
                    <tr>
                        <th>ID</th>

                        <th>
                            <div class="account-filter-header">
                                <a href="{{ $sortLink('transaction_type') }}" class="account-sort-link">
                                    収支区分 <span>{{ $sortMark('transaction_type') }}</span>
                                </a>
                                <button type="button" class="account-filter-button" data-filter="transaction_type">▼</button>
                            </div>
                        </th>

                        <th>
                            <div class="account-filter-header">
                                <a href="{{ $sortLink('account_category') }}" class="account-sort-link">
                                    会計カテゴリ <span>{{ $sortMark('account_category') }}</span>
                                </a>
                                <button type="button" class="account-filter-button" data-filter="account_category">▼</button>
                            </div>
                        </th>

                        <th>取引名</th>

                        <th>
                            <div class="account-filter-header">
                                <a href="{{ $sortLink('school') }}" class="account-sort-link">
                                    教室 <span>{{ $sortMark('school') }}</span>
                                </a>
                                <button type="button" class="account-filter-button" data-filter="school">▼</button>
                            </div>
                        </th>

                        <th>
                            <div class="account-filter-header">
                                <a href="{{ $sortLink('student') }}" class="account-sort-link">
                                    生徒 <span>{{ $sortMark('student') }}</span>
                                </a>
                                <button type="button" class="account-filter-button" data-filter="student">▼</button>
                            </div>
                        </th>

                        <th>
                            <a href="{{ $sortLink('scheduled_date') }}" class="account-sort-link">
                                取引予定日 <span>{{ $sortMark('scheduled_date') }}</span>
                            </a>
                        </th>

                        <th>
                            <div class="account-filter-header">
                                <a href="{{ $sortLink('payment_method') }}" class="account-sort-link">
                                    入出金方法 <span>{{ $sortMark('payment_method') }}</span>
                                </a>
                                <button type="button" class="account-filter-button" data-filter="payment_method">▼</button>
                            </div>
                        </th>

                        <th>
                            <a href="{{ $sortLink('transaction_date') }}" class="account-sort-link">
                                取引日 <span>{{ $sortMark('transaction_date') }}</span>
                            </a>
                        </th>

                        <th>割引前金額</th>

                        <th>割引額</th>

                        <th>
                            <a href="{{ $sortLink('amount') }}" class="account-sort-link">
                                取引額（税込） <span>{{ $sortMark('amount') }}</span>
                            </a>
                        </th>

                        <th>
                            <div class="account-filter-header">
                                <a href="{{ $sortLink('status') }}" class="account-sort-link">
                                    状態 <span>{{ $sortMark('status') }}</span>
                                </a>
                                <button type="button" class="account-filter-button" data-filter="status">▼</button>
                            </div>
                        </th>

                        <th>
                            <div class="account-filter-header">
                                <span>割引種別</span>
                                <button type="button" class="account-filter-button" data-filter="discount_type">▼</button>
                            </div>
                        </th>

                        <th>割引メモ</th>
                        <th>元データテーブル</th>
                        <th>元データID</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        @php
                            $transactionType = $transaction->accountCategory?->transaction_type ?? '-';
                            $statusLabels = [
                                'planned' => '取引中',
                                'confirmed' => '取引完了',
                                'cancelled' => '取消',
                            ];
                        @endphp

                        <tr class="{{ $transactionType === '収益' ? 'account-row-income' : 'account-row-expense' }}">
                            <td>{{ $transaction->id }}</td>

                            <td>
                                <span class="account-type-badge {{ $transactionType === '収益' ? 'income' : 'expense' }}">
                                    {{ $transactionType }}
                                </span>
                            </td>

                            <td>{{ $transaction->accountCategory?->name ?? '-' }}</td>
                            
                            <td>
                                <span
                                    class="account-ellipsis"
                                    title="{{ $transaction->transaction_name }}"
                                >
                                    {{ $transaction->transaction_name }}
                                </span>
                            </td>

                            <td>
                                <span
                                    class="account-ellipsis"
                                    title="{{ $transaction->school?->name ?? '-' }}"
                                >
                                    {{ $transaction->school?->name ?? '-' }}
                                </span>
                            </td>

                            <td>{{ $transaction->student?->name ?? '-' }}</td>
                            <td>{{ $transaction->scheduled_date ? \Carbon\Carbon::parse($transaction->scheduled_date)->format('Y/m/d') : '-' }}</td>
                            <td>{{ $transaction->paymentMethod?->name ?? '-' }}</td>
                            <td>{{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('Y/m/d') : '-' }}</td>
                            <td class="amount-cell">¥{{ number_format($transaction->before_discount_amount ?? $transaction->amount) }}</td>
                            <td class="amount-cell discount-cell">¥{{ number_format($transaction->discount_amount ?? 0) }}</td>
                            <td class="amount-cell final-amount">¥{{ number_format($transaction->amount) }}</td>

                            <td>
                                <span class="account-status-badge {{ $transaction->status }}">
                                    {{ $statusLabels[$transaction->status] ?? $transaction->status }}
                                </span>
                            </td>

                            <td class="discount-cell">{{ $transaction->discountType?->name ?? '-' }}</td>
                            
                            <td class="discount-cell">
                                <span
                                    class="account-ellipsis"
                                    title="{{ $transaction->discount_note ?? '-' }}"
                                >
                                    {{ $transaction->discount_note ?? '-' }}
                                </span>
                            </td>
                            
                            <td>
                                <span
                                    class="account-ellipsis"
                                    title="{{ $transaction->source_table ?? '-' }}"
                                >
                                    {{ $transaction->source_table ?? '-' }}
                                </span>
                            </td>

                            <td>{{ $transaction->source_id ?? '-' }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="account-detail-button"
                                    data-id="{{ $transaction->id }}"
                                    data-name="{{ $transaction->transaction_name }}"
                                    data-category="{{ $transaction->accountCategory?->name ?? '-' }}"
                                    data-type="{{ $transactionType }}"
                                    data-school="{{ $transaction->school?->name ?? '-' }}"
                                    data-student="{{ $transaction->student?->name ?? '-' }}"
                                    data-scheduled-date="{{ $transaction->scheduled_date }}"
                                    data-transaction-date="{{ $transaction->transaction_date }}"
                                    data-payment-method="{{ $transaction->paymentMethod?->name ?? '-' }}"
                                    data-status="{{ $statusLabels[$transaction->status] ?? $transaction->status }}"
                                    data-before-discount="{{ number_format($transaction->before_discount_amount ?? $transaction->amount) }}"
                                    data-discount-type="{{ $transaction->discountType?->name ?? '-' }}"
                                    data-discount-amount="{{ number_format($transaction->discount_amount ?? 0) }}"
                                    data-amount="{{ number_format($transaction->amount) }}"
                                    data-discount-note="{{ $transaction->discount_note ?? '-' }}"
                                    data-source-table="{{ $transaction->source_table ?? '-' }}"
                                    data-source-id="{{ $transaction->source_id ?? '-' }}"
                                    data-created-at="{{ $transaction->created_at ? $transaction->created_at->format('Y/m/d H:i') : '-' }}"
                                    data-updated-at="{{ $transaction->updated_at ? $transaction->updated_at->format('Y/m/d H:i') : '-' }}"
                                    data-memo="{{ $transaction->memo ?? '-' }}"
                                    data-cancelled-reason="{{ $transaction->cancelled_reason ?? '-' }}"
                                >
                                    詳細
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="17">会計台帳データがありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="account-pagination">
            {{ $transactions->links() }}
        </div>
    </section>

    <section class="account-chart-card">
        <div class="account-chart-header">
            <div>
                <h3>月次収支推移</h3>
                <p>収益・費用・累計利益の推移を確認できます。</p>
            </div>

            <div class="account-chart-periods">
                <a href="{{ request()->fullUrlWithQuery(['chart_period' => 'all']) }}" class="{{ $chartPeriod === 'all' ? 'active' : '' }}">全期間</a>
                <a href="{{ request()->fullUrlWithQuery(['chart_period' => '5years']) }}" class="{{ $chartPeriod === '5years' ? 'active' : '' }}">直近5年</a>
                <a href="{{ request()->fullUrlWithQuery(['chart_period' => '3years']) }}" class="{{ $chartPeriod === '3years' ? 'active' : '' }}">直近3年</a>
                <a href="{{ request()->fullUrlWithQuery(['chart_period' => '1year']) }}" class="{{ $chartPeriod === '1year' ? 'active' : '' }}">直近1年</a>

                <button type="button" id="accountChartToggle" class="account-chart-collapse-button" title="グラフを折りたたむ">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>

        <div class="account-chart-wrapper" id="accountChartBody">
            <canvas id="monthlyChart"></canvas>
        </div>
    </section>

</div>

<div class="account-detail-modal" id="accountDetailModal">
    <div class="account-detail-content">
        <div class="account-detail-header">
            <h3>取引詳細</h3>
            <button type="button" id="accountDetailClose">×</button>
        </div>

        <div id="accountDetailBody"></div>
    </div>
</div>

<div class="account-column-modal" id="accountColumnModal">
    <div class="account-column-content">
        <div class="account-column-header">
            <h3>表示項目設定</h3>
            <button type="button" id="accountColumnClose">×</button>
        </div>

        <div class="account-column-list">
            <label><input type="checkbox" data-column="4" checked> 教室</label>
            <label><input type="checkbox" data-column="5" checked> 生徒</label>
            <label><input type="checkbox" data-column="7" checked> 入出金方法</label>

            <label><input type="checkbox" data-column="8" checked> 取引日</label>
            <label><input type="checkbox" data-column="9" checked> 割引前金額</label>
            <label><input type="checkbox" data-column="10" checked> 割引額</label>

            <label><input type="checkbox" data-column="13" checked> 割引種別</label>

            <label><input type="checkbox" data-column="14"> 割引メモ</label>
            <label><input type="checkbox" data-column="15"> 元データテーブル</label>
            <label><input type="checkbox" data-column="16"> 元データID</label>
        </div>
    </div>
</div>

@endsection