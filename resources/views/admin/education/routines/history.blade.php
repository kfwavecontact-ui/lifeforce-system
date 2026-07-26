@extends('layouts.admin')

@section('title', '割当済ルーティン状況')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/education-routines.css') }}">
@endpush

@section('content')
<div class="er-page" data-page="history-items">
    <header class="er-head">
        <div>
            <h1>割当済ルーティン状況</h1>
            <p>生徒へ割り当てられたルーティンアイテムを1行単位で表示し、実施中・完了・停止をまとめて確認します。</p>
        </div>
        <a class="er-primary" href="{{ route('admin.education.routines.history.export', request()->query()) }}">CSV出力</a>
    </header>

    @include('admin.education.routines._tabs')

    <section class="er-history-guide" role="note">
        <strong>確認単位：割当済みルーティンアイテム</strong>
        <span>所属ルーティンは補助情報として表示します。状態を切り替えると、現在実施中の取組から完了・停止した取組まで確認できます。</span>
    </section>

    <nav class="er-status-segments" aria-label="状態のクイック絞り込み">
        @php $statusQuery = request()->except(['status', 'page']); @endphp
        <a class="{{ request('status') === null || request('status') === '' ? 'active' : '' }}" href="{{ route('admin.education.routines.history', $statusQuery) }}">すべて</a>
        <a class="{{ request('status') === 'active' ? 'active' : '' }}" href="{{ route('admin.education.routines.history', array_merge($statusQuery, ['status' => 'active'])) }}">実施中</a>
        <a class="{{ request('status') === 'completed' ? 'active' : '' }}" href="{{ route('admin.education.routines.history', array_merge($statusQuery, ['status' => 'completed'])) }}">完了</a>
        <a class="{{ request('status') === 'stopped' ? 'active' : '' }}" href="{{ route('admin.education.routines.history', array_merge($statusQuery, ['status' => 'stopped'])) }}">停止</a>
    </nav>

    <form class="er-filter er-history-filter" method="get">
        <div class="er-history-filter-row primary-row">
            <label class="keyword-field">
                <span>生徒・ルーティン・アイテムを検索</span>
                <input name="keyword" value="{{ request('keyword') }}" placeholder="生徒名・生徒ID・ルーティン名・アイテム名・コード">
            </label>
            <label>
                <span>状態</span>
                <select name="status">
                    <option value="">すべて（実施中・完了・停止）</option>
                    <option value="active" @selected(request('status') === 'active')>実施中</option>
                    <option value="completed" @selected(request('status') === 'completed')>完了</option>
                    <option value="stopped" @selected(request('status') === 'stopped')>停止</option>
                </select>
            </label>
            <label>
                <span>開始日（以上）</span>
                <input type="date" name="from" value="{{ request('from') }}">
            </label>
            <label>
                <span>終了日（以下）</span>
                <input type="date" name="to" value="{{ request('to') }}">
            </label>
            <label>
                <span>教室</span>
                <select name="school_id">
                    <option value="">すべて</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" @selected((string) request('school_id') === (string) $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>学年</span>
                <select name="grade_id">
                    <option value="">すべて</option>
                    @foreach($grades as $grade)
                        <option value="{{ $grade->id }}" @selected((string) request('grade_id') === (string) $grade->id)>{{ $grade->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="er-history-filter-row secondary-row">
            <label>
                <span>必須区分</span>
                <select name="required_type">
                    <option value="">すべて</option>
                    <option value="required" @selected(request('required_type') === 'required')>必須</option>
                    <option value="optional" @selected(request('required_type') === 'optional')>任意</option>
                </select>
            </label>
            <label>
                <span>並び順</span>
                <select name="sort">
                    <option value="updated_desc" @selected(request('sort', 'updated_desc') === 'updated_desc')>更新が新しい順</option>
                    <option value="student_asc" @selected(request('sort') === 'student_asc')>生徒名順</option>
                    <option value="start_desc" @selected(request('sort') === 'start_desc')>開始日が新しい順</option>
                    <option value="progress_desc" @selected(request('sort') === 'progress_desc')>進捗率が高い順</option>
                    <option value="activity_desc" @selected(request('sort') === 'activity_desc')>最終実施が新しい順</option>
                </select>
            </label>
            <label>
                <span>表示件数</span>
                <select name="per_page">
                    @foreach([25, 50, 100] as $count)
                        <option value="{{ $count }}" @selected((int) request('per_page', 50) === $count)>{{ $count }}件</option>
                    @endforeach
                </select>
            </label>
            <div class="er-filter-actions">
                <button class="er-search-button" type="submit">検索</button>
                <a class="er-clear-button" href="{{ route('admin.education.routines.history') }}">クリア</a>
            </div>
        </div>
    </form>

    <div class="er-result-summary">
        全{{ number_format($rows->total()) }}件中
        {{ $rows->total() ? number_format($rows->firstItem()) : 0 }}〜{{ $rows->total() ? number_format($rows->lastItem()) : 0 }}件を表示
    </div>

    <section class="er-table-card er-history-item-table-card">
        <div class="er-table-scroll js-synced-table-scroll">
            <table class="er-history-item-table">
                <thead>
                    <tr>
                        <th class="sticky-col sticky-col-1">生徒</th>
                        <th class="sticky-col sticky-col-2">教室・学年</th>
                        <th class="sticky-col sticky-col-3">所属ルーティン</th>
                        <th>ルーティンアイテム</th>
                        <th>元アイテム</th>
                        <th>必須</th>
                        <th>期間</th>
                        <th>状態</th>
                        <th>達成日数</th>
                        <th>進捗率</th>
                        <th>学習時間</th>
                        <th>平均正答率</th>
                        <th>最終実施</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <th class="sticky-col sticky-col-1">
                                <strong>{{ $row->student_name }}</strong>
                                <small>{{ $row->student_code ?: 'ID未設定' }}</small>
                            </th>
                            <td class="sticky-col sticky-col-2">
                                <span class="er-cell-secondary">{{ $row->school_name ?: '教室未設定' }}</span>
                                <small>{{ $row->grade_name ?: '学年未設定' }}</small>
                            </td>
                            <td class="sticky-col sticky-col-3">
                                <span class="er-cell-routine">{{ $row->routine_name }}</span>
                                <small>{{ $row->package_code ?: '個別設定' }}</small>
                            </td>
                            <td>
                                <span class="er-cell-item">{{ $row->item_name }}</span>
                                <small>割当時の名称@if($row->memo)・<span class="er-note">メモあり</span>@endif</small>
                            </td>
                            <td>
                                <span class="er-cell-secondary">{{ $row->master_item_name ?: '—' }}</span>
                                <small>{{ $row->content_code ?: 'コード未設定' }}</small>
                            </td>
                            <td><span class="er-badge {{ $row->is_required ? 'blue' : 'gray' }}">{{ $row->is_required ? '必須' : '任意' }}</span></td>
                            <td><span class="er-period-label">{{ $row->effective_start_date ?: '—' }}<br><small>〜 {{ $row->completed_at ? \Carbon\Carbon::parse($row->completed_at)->toDateString() : '継続中' }}</small></span></td>
                            <td><span class="er-badge {{ $row->badge_class }}">{{ $row->display_status }}</span></td>
                            <td>{{ number_format((int) $row->achieved_days) }}日 / {{ $row->required_days ? number_format((int) $row->required_days).'日' : '—' }}</td>
                            <td>
                                @if($row->progress_rate === null)
                                    —
                                @else
                                    <div class="er-progress-value"><div class="er-mini-progress"><span style="width: {{ $row->progress_rate }}%"></span></div><strong>{{ $row->progress_rate }}%</strong></div>
                                @endif
                            </td>
                            <td>{{ $row->study_time_label }}</td>
                            <td>{{ $row->average_accuracy_rate === null ? '—' : round($row->average_accuracy_rate, 1).'%' }}</td>
                            <td class="er-date-time">{{ $row->last_activity_at ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="er-empty">条件に一致する割当済みルーティンアイテムはありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('admin.education.routines._compact-pagination', ['paginator' => $rows])

    <script defer src="{{ asset('js/admin/education-routines.js') }}?v={{ file_exists(public_path('js/admin/education-routines.js')) ? filemtime(public_path('js/admin/education-routines.js')) : time() }}"></script>
</div>
@endsection
