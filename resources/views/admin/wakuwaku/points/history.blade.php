@extends('layouts.admin')

@section('title', 'ポイント履歴')

@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/point-history.css') }}">

@php
    $sortLink = function (string $key) use ($sort, $direction) {
        $nextDirection = $sort === $key && $direction === 'asc' ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection, 'page' => null]);
    };
    $sortMark = fn (string $key) => $sort !== $key ? '↕' : ($direction === 'asc' ? '↑' : '↓');
    $netClass = $summary['net'] > 0 ? 'is-positive' : ($summary['net'] < 0 ? 'is-negative' : 'is-zero');
@endphp

<div class="point-history-page">
    <header class="point-history-header">
        <div>
            <p class="point-history-breadcrumb">わくわく ＞ ポイント ＞ ポイント履歴</p>
            <h1>ポイント履歴</h1>
            <p class="point-history-description">生徒ごとのポイント獲得・利用履歴と、変動後の残高を確認します。</p>
        </div>
        <div class="point-history-header-actions">
            <a href="{{ route('admin.wakuwaku.points.index') }}" class="point-history-secondary-button">ポイント残高</a>
            <a href="{{ route('admin.wakuwaku.points.adjustment') }}" class="point-history-primary-button">ポイント調整</a>
        </div>
    </header>

    <section class="point-history-summary-grid" aria-label="ポイント履歴集計">
        <article class="point-history-summary-card is-earned">
            <span>対象期間の獲得</span>
            <strong>{{ $summary['earned'] > 0 ? '+' : '' }}{{ number_format($summary['earned']) }}<small> pt</small></strong>
        </article>
        <article class="point-history-summary-card is-used">
            <span>対象期間の利用</span>
            <strong>{{ $summary['used'] > 0 ? '-' : '' }}{{ number_format($summary['used']) }}<small> pt</small></strong>
        </article>
        <article class="point-history-summary-card {{ $netClass }}">
            <span>対象期間の差引</span>
            <strong>{{ $summary['net'] > 0 ? '+' : '' }}{{ number_format($summary['net']) }}<small> pt</small></strong>
        </article>
        <article class="point-history-summary-card">
            <span>対象履歴</span>
            <strong>{{ number_format($summary['count']) }}<small> 件</small></strong>
        </article>
    </section>

    <section class="point-history-filter-card">
        <form method="GET" action="{{ route('admin.wakuwaku.points.history') }}" class="point-history-filter-form" id="pointHistoryFilterForm">
            <label class="point-history-keyword-field">
                <span>キーワード</span>
                <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="生徒ID・氏名・履歴内容・理由">
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
                <span>ポイント区分</span>
                <select name="point_type">
                    <option value="">すべて</option>
                    <option value="earned" @selected(($filters['point_type'] ?? '') === 'earned')>獲得</option>
                    <option value="used" @selected(($filters['point_type'] ?? '') === 'used')>利用</option>
                </select>
            </label>
            <label>
                <span>開始日</span>
                <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
            </label>
            <label>
                <span>終了日</span>
                <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
            </label>
            <label>
                <span>並び順</span>
                <select name="sort">
                    <option value="occurred_at" @selected($sort === 'occurred_at')>発生日時</option>
                    <option value="student_code" @selected($sort === 'student_code')>生徒ID</option>
                    <option value="name" @selected($sort === 'name')>氏名</option>
                    <option value="grade" @selected($sort === 'grade')>学年</option>
                    <option value="points" @selected($sort === 'points')>ポイント</option>
                    <option value="balance_after" @selected($sort === 'balance_after')>変動後残高</option>
                    <option value="event_type" @selected($sort === 'event_type')>履歴内容</option>
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
            <div class="point-history-filter-actions">
                <button type="submit" class="point-history-primary-button">検索</button>
                <a href="{{ route('admin.wakuwaku.points.history') }}" class="point-history-clear-button">条件クリア</a>
            </div>
        </form>
        @error('end_date')
            <p class="point-history-filter-error">終了日は開始日以降の日付を指定してください。</p>
        @enderror
    </section>

    <section class="point-history-list-card">
        <div class="point-history-list-header">
            <div>
                <h2>ポイント履歴一覧</h2>
                <p>
                    @if ($histories->total() > 0)
                        {{ number_format($histories->firstItem()) }}～{{ number_format($histories->lastItem()) }}件表示（全{{ number_format($histories->total()) }}件）
                    @else
                        0件表示（全0件）
                    @endif
                </p>
            </div>
            <p class="point-history-period">
                <span>対象期間</span>
                <strong>{{ !empty($filters['start_date']) ? \Carbon\Carbon::parse($filters['start_date'])->format('Y/m/d') : '指定なし' }} ～ {{ !empty($filters['end_date']) ? \Carbon\Carbon::parse($filters['end_date'])->format('Y/m/d') : '指定なし' }}</strong>
            </p>
        </div>

        <div class="point-history-table-wrap">
            <table class="point-history-table">
                <thead>
                    <tr>
                        <th><a href="{{ $sortLink('occurred_at') }}">発生日時 <span>{{ $sortMark('occurred_at') }}</span></a></th>
                        <th><a href="{{ $sortLink('student_code') }}">生徒ID <span>{{ $sortMark('student_code') }}</span></a></th>
                        <th><a href="{{ $sortLink('name') }}">氏名 <span>{{ $sortMark('name') }}</span></a></th>
                        <th><a href="{{ $sortLink('grade') }}">学年 <span>{{ $sortMark('grade') }}</span></a></th>
                        <th>区分</th>
                        <th><a href="{{ $sortLink('event_type') }}">履歴内容 <span>{{ $sortMark('event_type') }}</span></a></th>
                        <th class="point-history-number"><a href="{{ $sortLink('points') }}">ポイント <span>{{ $sortMark('points') }}</span></a></th>
                        <th class="point-history-number"><a href="{{ $sortLink('balance_after') }}">変動後残高 <span>{{ $sortMark('balance_after') }}</span></a></th>
                        <th>理由</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($histories as $history)
                        @php
                            $isEarned = $history->points > 0;
                            $pointClass = $isEarned ? 'is-earned' : 'is-used';
                            $balanceClass = $history->balance_after < 0 ? 'is-negative' : ($history->balance_after == 0 ? 'is-zero' : 'is-positive');
                        @endphp
                        <tr>
                            <td class="point-history-date">{{ \Carbon\Carbon::parse($history->occurred_at)->format('Y/m/d H:i') }}</td>
                            <td class="point-history-code">{{ $history->student_code }}</td>
                            <td>
                                <strong>{{ $history->last_name }} {{ $history->first_name }}</strong>
                                @if ($history->last_name_kana || $history->first_name_kana)
                                    <small>{{ $history->last_name_kana }} {{ $history->first_name_kana }}</small>
                                @endif
                            </td>
                            <td>{{ $history->grade_name ?: '—' }}</td>
                            <td><span class="point-history-type {{ $pointClass }}">{{ $isEarned ? '獲得' : '利用' }}</span></td>
                            <td>
                                <span class="point-history-event" title="{{ $history->event_type_name ?: $history->event_type }}">
                                    {{ $history->event_type_name ?: $history->event_type }}
                                </span>
                            </td>
                            <td class="point-history-number point-history-points {{ $pointClass }}">
                                {{ $history->points > 0 ? '+' : '' }}{{ number_format($history->points) }} pt
                            </td>
                            <td class="point-history-number point-history-balance {{ $balanceClass }}">{{ number_format($history->balance_after) }} pt</td>
                            <td>
                                <span class="point-history-reason" title="{{ $history->reason }}">{{ $history->reason ? \Illuminate\Support\Str::limit($history->reason, 18) : '—' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="point-history-empty">条件に一致するポイント履歴がありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="point-history-pagination">
            <p>
                @if ($histories->total() > 0)
                    {{ number_format($histories->firstItem()) }}～{{ number_format($histories->lastItem()) }}件表示（全{{ number_format($histories->total()) }}件）
                @else
                    0件表示（全0件）
                @endif
            </p>
            {{ $histories->links() }}
        </div>
    </section>
</div>

<div class="point-history-toast" id="pointHistoryToast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/admin/wakuwaku/point-history.js') }}"></script>
@endsection
