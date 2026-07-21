@extends('layouts.admin')

@section('title', 'ポイント残高')

@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/point-balances.css') }}">

@php
    $sortLink = function (string $key) use ($sort, $direction) {
        $nextDirection = $sort === $key && $direction === 'asc' ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection, 'page' => null]);
    };
    $sortMark = fn (string $key) => $sort !== $key ? '↕' : ($direction === 'asc' ? '↑' : '↓');
@endphp

<div class="point-balance-page">
    <header class="point-balance-header">
        <div>
            <p class="point-balance-breadcrumb">わくわく ＞ ポイント ＞ ポイント残高</p>
            <h1>ポイント残高</h1>
            <p class="point-balance-description">生徒ごとの現在ポイントと、今月の獲得・利用状況を確認します。</p>
        </div>
        <div class="point-balance-header-actions">
            <a href="{{ route('admin.wakuwaku.points.history') }}" class="point-balance-secondary-button">ポイント履歴</a>
            <a href="{{ route('admin.wakuwaku.points.adjustment') }}" class="point-balance-primary-button">ポイント調整</a>
        </div>
    </header>

    <section class="point-balance-summary-grid" aria-label="ポイント集計">
        <article class="point-balance-summary-card">
            <span>総保有ポイント</span>
            <strong>{{ number_format($summary['total_points']) }}<small> pt</small></strong>
        </article>
        <article class="point-balance-summary-card point-balance-summary-earned {{ $summary['month_earned'] > 0 ? 'has-value' : 'is-zero' }}">
            <span>今月獲得</span>
            <strong>{{ number_format($summary['month_earned']) }}<small> pt</small></strong>
        </article>
        <article class="point-balance-summary-card point-balance-summary-used {{ $summary['month_used'] > 0 ? 'has-value' : 'is-zero' }}">
            <span>今月利用</span>
            <strong>{{ number_format($summary['month_used']) }}<small> pt</small></strong>
        </article>
        <article class="point-balance-summary-card">
            <span>ポイント管理対象者数</span>
            <strong>{{ number_format($summary['managed_student_count']) }}<small> 人</small></strong>
        </article>
    </section>

    <section class="point-balance-filter-card">
        <form method="GET" action="{{ route('admin.wakuwaku.points.index') }}" class="point-balance-filter-form">
            <label class="point-balance-keyword-field">
                <span>キーワード</span>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="生徒ID・氏名・ふりがな">
            </label>
            <label>
                <span>学年</span>
                <select name="grade_id">
                    <option value="">すべて</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" @selected(($filters['grade_id'] ?? '') == $grade->id)>{{ $grade->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>在籍状態</span>
                <select name="enrollment_status_id">
                    <option value="">すべて</option>
                    @foreach ($enrollmentStatuses as $status)
                        <option value="{{ $status->id }}" @selected(($filters['enrollment_status_id'] ?? '') == $status->id)>{{ $status->status }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>最低ポイント</span>
                <input type="number" name="min_points" value="{{ $filters['min_points'] ?? '' }}" step="1" placeholder="例：0">
            </label>
            <label>
                <span>最高ポイント</span>
                <input type="number" name="max_points" value="{{ $filters['max_points'] ?? '' }}" step="1" placeholder="例：3000">
            </label>
            <label>
                <span>並び順</span>
                <select name="sort">
                    <option value="student_code" @selected($sort === 'student_code')>生徒ID</option>
                    <option value="name" @selected($sort === 'name')>氏名</option>
                    <option value="grade" @selected($sort === 'grade')>学年</option>
                    <option value="current_points" @selected($sort === 'current_points')>現在ポイント</option>
                    <option value="month_earned" @selected($sort === 'month_earned')>今月獲得</option>
                    <option value="month_used" @selected($sort === 'month_used')>今月利用</option>
                    <option value="last_occurred_at" @selected($sort === 'last_occurred_at')>最終変動日</option>
                </select>
                <input type="hidden" name="direction" value="{{ $direction }}">
            </label>
            <label>
                <span>表示件数</span>
                <select name="per_page">
                    @foreach ([20, 50, 100] as $count)
                        <option value="{{ $count }}" @selected((int) ($filters['per_page'] ?? 50) === $count)>{{ $count }}件</option>
                    @endforeach
                </select>
            </label>
            <div class="point-balance-filter-actions">
                <button type="submit" class="point-balance-primary-button">検索</button>
                <a href="{{ route('admin.wakuwaku.points.index') }}" class="point-balance-clear-button">条件クリア</a>
            </div>
        </form>
    </section>

    <section class="point-balance-list-card">
        <div class="point-balance-list-header">
            <div>
                <h2>生徒別ポイント残高</h2>
                <p>全{{ number_format($balances->total()) }}件</p>
            </div>
        </div>

        <div class="point-balance-table-wrap">
            <table class="point-balance-table">
                <thead>
                    <tr>
                        <th><a href="{{ $sortLink('student_code') }}">生徒ID <span>{{ $sortMark('student_code') }}</span></a></th>
                        <th><a href="{{ $sortLink('name') }}">氏名 <span>{{ $sortMark('name') }}</span></a></th>
                        <th>教室</th>
                        <th><a href="{{ $sortLink('grade') }}">学年 <span>{{ $sortMark('grade') }}</span></a></th>
                        <th>在籍状態</th>
                        <th class="point-balance-number"><a href="{{ $sortLink('current_points') }}">現在ポイント <span>{{ $sortMark('current_points') }}</span></a></th>
                        <th class="point-balance-number"><a href="{{ $sortLink('month_earned') }}">今月獲得 <span>{{ $sortMark('month_earned') }}</span></a></th>
                        <th class="point-balance-number"><a href="{{ $sortLink('month_used') }}">今月利用 <span>{{ $sortMark('month_used') }}</span></a></th>
                        <th><a href="{{ $sortLink('last_occurred_at') }}">最終変動日 <span>{{ $sortMark('last_occurred_at') }}</span></a></th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($balances as $balance)
                        @php
                            $pointClass = $balance->current_points < 0 ? 'is-negative' : ($balance->current_points === 0 ? 'is-zero' : 'is-positive');
                            $earnedClass = $balance->month_earned > 0 ? 'has-value' : 'is-zero';
                            $usedClass = $balance->month_used > 0 ? 'has-value' : 'is-zero';
                        @endphp
                        <tr>
                            <td class="point-balance-code">{{ $balance->student_code }}</td>
                            <td>
                                <strong>{{ $balance->last_name }} {{ $balance->first_name }}</strong>
                                @if ($balance->last_name_kana || $balance->first_name_kana)
                                    <small>{{ $balance->last_name_kana }} {{ $balance->first_name_kana }}</small>
                                @endif
                            </td>
                            <td>{{ $balance->classroom_names ?: '—' }}</td>
                            <td>{{ $balance->grade_name ?: '—' }}</td>
                            <td><span class="point-balance-status {{ $balance->is_active ? 'is-active' : 'is-inactive' }}">{{ $balance->enrollment_status_name ?: '未設定' }}</span></td>
                            <td class="point-balance-number point-balance-current {{ $pointClass }}">{{ number_format($balance->current_points) }} pt</td>
                            <td class="point-balance-number point-balance-earned {{ $earnedClass }}">{{ number_format($balance->month_earned) }} pt</td>
                            <td class="point-balance-number point-balance-used {{ $usedClass }}">{{ number_format($balance->month_used) }} pt</td>
                            <td>{{ $balance->last_occurred_at ? \Carbon\Carbon::parse($balance->last_occurred_at)->format('Y/m/d') : '—' }}</td>
                            <td>
                                <div class="point-balance-row-actions">
                                    <a href="{{ route('admin.wakuwaku.points.history', ['student_id' => $balance->id]) }}" title="ポイント履歴" aria-label="{{ $balance->last_name }} {{ $balance->first_name }}さんのポイント履歴">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm2 5h6M9 12h6M9 16h4"/></svg>
                                        <span>履歴</span>
                                    </a>
                                    <a href="{{ route('admin.wakuwaku.points.adjustment', ['student_id' => $balance->id]) }}" title="ポイント調整" aria-label="{{ $balance->last_name }} {{ $balance->first_name }}さんのポイント調整">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.5-1 9.8-9.8a2.1 2.1 0 0 0-3-3L5.5 16 4 20Zm10-12 3 3"/></svg>
                                        <span>調整</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="point-balance-empty">条件に一致する生徒がいません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="point-balance-pagination">{{ $balances->links() }}</div>
    </section>
</div>

<div class="point-balance-toast" id="pointBalanceToast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/admin/wakuwaku/point-balances.js') }}"></script>
@endsection
