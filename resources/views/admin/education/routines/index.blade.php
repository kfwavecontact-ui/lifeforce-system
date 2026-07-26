@extends('layouts.admin')

@section('title', 'ルーティン割当')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/education-routines.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/student-karte-routine-exact.css') }}">
@endpush

@section('content')
<div class="er-page" data-page="assignment">
    <header class="er-head">
        <div>
            <h1>ルーティン割当</h1>
            <p>生徒を選んでルーティンを割り当てる、またはルーティンを選んで複数の生徒へ割り当てます。</p>
        </div>
    </header>

    @include('admin.education.routines._tabs')

    @if(session('status'))
        <div class="er-alert">{{ session('status') }}</div>
    @endif

    <nav class="er-switch" aria-label="表示軸">
        <a class="{{ $view === 'students' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'students', 'package_id' => null]) }}">生徒にルーティンを割り当てる</a>
        <a class="{{ $view === 'routines' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'routines', 'student_id' => null]) }}">ルーティンに生徒を割り当てる</a>
    </nav>

    @if($view === 'students')
        <form class="er-filter er-filter-grid assignment-filter" method="get">
            <input type="hidden" name="view" value="students">
            <label><span>生徒名・生徒ID</span><input name="student_keyword" value="{{ request('student_keyword') }}" placeholder="氏名・生徒ID"></label>
            <label>
                <span>教室</span>
                <select name="school_id">
                    <option value="">全教室</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" @selected((string) request('school_id') === (string) $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>学年</span>
                <select name="grade_id">
                    <option value="">全学年</option>
                    @foreach($grades as $grade)
                        <option value="{{ $grade->id }}" @selected((string) request('grade_id') === (string) $grade->id)>{{ $grade->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>割当状態</span>
                <select name="assignment_status">
                    <option value="">すべて</option>
                    <option value="assigned" @selected(request('assignment_status') === 'assigned')>割当あり</option>
                    <option value="unassigned" @selected(request('assignment_status') === 'unassigned')>割当なし</option>
                </select>
            </label>
            <label>
                <span>並び順</span>
                <select name="student_sort">
                    <option value="">氏名順</option>
                    <option value="student_code" @selected(request('student_sort') === 'student_code')>生徒ID順</option>
                    <option value="grade" @selected(request('student_sort') === 'grade')>学年順</option>
                    <option value="attention" @selected(request('student_sort') === 'attention')>要確認順</option>
                </select>
            </label>
            <label>
                <span>表示件数</span>
                <select name="student_per_page">
                    @foreach([25, 50, 100] as $count)
                        <option value="{{ $count }}" @selected((int) request('student_per_page', 50) === $count)>{{ $count }}件</option>
                    @endforeach
                </select>
            </label>
            <label class="er-check-field"><input type="checkbox" name="attention_only" value="1" @checked(request()->boolean('attention_only'))><span>要確認のみ</span></label>
            <div class="er-filter-actions">
                <button class="er-search-button" type="submit">検索</button>
                <a class="er-clear-button" href="{{ route('admin.education.routines.index', ['view' => 'students']) }}">クリア</a>
            </div>
        </form>

        <div class="er-grid three er-assignment-grid">
            <section class="er-panel er-column-panel">
                <div class="er-panel-title"><span>生徒一覧</span><span>{{ number_format($students->total()) }}名</span></div>
                <div class="er-list er-column-scroll">
                    @forelse($students as $student)
                        <a class="er-student {{ optional($selectedStudent)->id === $student->id ? 'selected' : '' }}" href="{{ request()->fullUrlWithQuery(['student_id' => $student->id, 'student_page' => $students->currentPage()]) }}">
                            <strong>{{ $student->student_name }}</strong>
                            <small>{{ $student->student_code }}｜{{ $student->grade_name ?: '学年未設定' }}｜{{ $student->school_name ?: '教室未設定' }}</small>
                            <div>
                                <span>{{ $student->routine_count }}ルーティン</span>
                                <span>{{ $student->item_count }}アイテム</span>
                            </div>
                        </a>
                    @empty
                        <div class="er-empty">条件に一致する生徒はいません。</div>
                    @endforelse
                </div>
                <div class="er-column-footer">@include('admin.education.routines._compact-pagination', ['paginator' => $students])</div>
            </section>

            <section class="er-panel er-column-panel er-available-packages-panel">
                <div class="er-panel-title"><span>割当可能なルーティン</span><span>{{ number_format($packages->total()) }}件</span></div>
                <nav class="er-package-modes">
                    <a class="{{ $packageMode === 'all' ? 'active' : '' }}" title="通常の表示順です" href="{{ request()->fullUrlWithQuery(['package_mode' => 'all', 'package_page' => null]) }}">すべて</a>
                    <a class="{{ $packageMode === 'popular' ? 'active' : '' }}" title="割当1回以上のルーティンを、割当回数が多い順に表示します" href="{{ request()->fullUrlWithQuery(['package_mode' => 'popular', 'package_page' => null]) }}">割当回数が多い</a>
                    <a class="{{ $packageMode === 'recent' ? 'active' : '' }}" title="同じルーティンを重複させず、最新の割当順に表示します" href="{{ request()->fullUrlWithQuery(['package_mode' => 'recent', 'package_page' => null]) }}">最近割り当てた</a>
                </nav>
                <form class="er-mini-filter advanced" method="get">
                    <input type="hidden" name="view" value="students">
                    <input type="hidden" name="student_id" value="{{ optional($selectedStudent)->id }}">
                    <input type="hidden" name="package_mode" value="{{ $packageMode }}">
                    <input name="package_keyword" value="{{ request('package_keyword') }}" placeholder="名称・コード・説明・タグ・アイテム">
                    <input name="package_grade" value="{{ request('package_grade') }}" placeholder="対象学年">
                    <input name="package_level" value="{{ request('package_level') }}" placeholder="難易度・レベル">
                    <button type="submit">検索</button>
                    <a class="er-clear-button" href="{{ route('admin.education.routines.index', ['view' => 'students', 'student_id' => optional($selectedStudent)->id, 'package_mode' => $packageMode]) }}">クリア</a>
                </form>
                <div class="er-list er-column-scroll">
                    @forelse($packages as $package)
                        <article class="er-package-card">
                            <div class="er-package-card-head">
                                <div class="er-package-card-title"><h3>{{ $package->name }}</h3><p><span>{{ $package->package_code ?: 'コード未設定' }}</span><span>{{ $package->target_grade ?: '対象学年未設定' }}</span></p></div>
                                <span class="er-package-use-count">全体割当{{ $package->assignment_count }}回</span>
                            </div>
                            @if($package->assigned_to_selected_student ?? false)<div class="er-assigned-label">この生徒に割当済み</div>@endif
                            @if($package->description)<p class="er-package-description">{{ Str::limit($package->description, 70) }}</p>@endif
                            <div class="er-package-stats"><div><small>アイテム</small><strong>{{ $package->item_count }}件</strong></div><div><small>1日の目安</small><strong>約{{ $package->total_minutes }}分</strong></div><div><small>推奨日数</small><strong>{{ $package->recommended_days ?: '—' }}日</strong></div></div>
                            <div class="er-package-item-preview">
                                <div class="er-package-item-preview-head"><strong>構成ルーティンアイテム</strong><span>{{ $package->items->count() }}件</span></div>
                                @forelse($package->items->take(3) as $item)
                                    <div class="er-package-item-row"><span class="er-package-item-name">{{ $item->item_name ?: $item->master_name }}</span><span class="er-package-item-meta">{{ $item->is_required ? '必須' : '任意' }}・{{ $item->estimated_minutes ?: 0 }}分</span></div>
                                @empty
                                    <div class="er-package-item-empty">構成アイテムはありません。</div>
                                @endforelse
                                @if($package->items->count() > 3)<div class="er-package-item-more">ほか{{ $package->items->count() - 3 }}件</div>@endif
                            </div>
                            @php
                                $warningCount = $package->items->filter(function ($item) {
                                    $isPageReady = in_array(
                                        $item->learning_page_status,
                                        ['作成済み', 'created', 'completed', 'published'],
                                        true
                                    );

                                    return !$isPageReady && !($item->publish_status ?? false);
                                })->count();
                            @endphp
                            @if($warningCount)<div class="er-warning-inline">学習ページ要確認 {{ $warningCount }}件</div>@endif
                            <div class="er-package-card-actions"><button type="button" class="er-primary js-open-assign" data-package-id="{{ $package->id }}">割り当てる</button></div>
                        </article>
                    @empty
                        <div class="er-empty">条件に一致するルーティンはありません。</div>
                    @endforelse
                </div>
                <div class="er-column-footer">@include('admin.education.routines._compact-pagination', ['paginator' => $packages])</div>
            </section>
            <section class="er-panel er-main er-column-panel er-current-assignments-panel">
                @if($selectedStudent)
                    <div class="er-panel-title">
                        <span>{{ $selectedStudent->last_name }} {{ $selectedStudent->first_name }}さんに現在割り当てられているルーティン</span>
                        <a class="er-clear-button" href="{{ route('admin.students.karte.show', ['student' => $selectedStudent->id, 'tab' => 'routine']) }}" target="_blank" rel="noopener noreferrer">生徒カルテを開く</a>
                    </div>
                    <div class="er-profile er-current-assignment-heading">
                        <p>{{ $selectedStudent->student_code }}｜{{ $selectedStudent->grade_name ?: '学年未設定' }}｜{{ $selectedStudent->school_name ?: '教室未設定' }}</p>
                    </div>
                    <div class="er-column-scroll er-routine-scroll">
                        @include('admin.partials.current-routine-table', [
                            'student' => $selectedStudent,
                            'activeRoutines' => $selectedStudent->routines,
                            'todayStatuses' => $selectedStudent->today_statuses,
                            'allStatuses' => $selectedStudent->all_statuses,
                            'calendarStatuses' => $selectedStudent->calendar_statuses,
                            'showTodayActual' => false,
                            'showActions' => false,
                            'showRoutineActions' => false,
                        ])
                    </div>
                @else
                    <div class="er-empty large">左の生徒一覧から確認する生徒を選択してください。</div>
                @endif
            </section>

        </div>

        @include('admin.education.routines._assign-modal')
    @else
        <form class="er-filter er-filter-grid" method="get">
            <input type="hidden" name="view" value="routines">
            <label><span>ルーティン検索</span><input name="package_keyword" value="{{ request('package_keyword') }}" placeholder="名称・コード・説明・タグ・構成アイテム名"></label>
            <label><span>対象学年</span><input name="package_grade" value="{{ request('package_grade') }}" placeholder="例：小1"></label>
            <label><span>難易度・レベル</span><input name="package_level" value="{{ request('package_level') }}"></label>
            <label><span>利用状態</span><select name="package_active"><option value="">すべて</option><option value="active" @selected(request('package_active') === 'active')>有効</option><option value="inactive" @selected(request('package_active') === 'inactive')>無効</option></select></label>
            <div class="er-filter-actions er-routine-search-actions"><button class="er-search-button" type="submit">検索</button><a class="er-clear-button" href="{{ route('admin.education.routines.index', ['view' => 'routines']) }}">クリア</a></div>
        </form>

        <div class="er-grid three er-assignment-grid routine-view">
            <section class="er-panel er-column-panel">
                <div class="er-panel-title"><span>確認するルーティンを選択</span><span>{{ number_format($packages->total()) }}件</span></div>
                <div class="er-list er-column-scroll">
                    @forelse($packages as $package)
                        <a class="er-package-link {{ optional($selectedPackage)->id === $package->id ? 'selected' : '' }}" href="{{ request()->fullUrlWithQuery(['package_id' => $package->id, 'package_page' => $packages->currentPage()]) }}">
                            <strong>{{ $package->name }}</strong><small>{{ $package->package_code ?: 'コード未設定' }}</small>
                            <div class="er-metrics"><span>{{ $package->item_count }}件</span><span>約{{ $package->total_minutes }}分</span><span>割当{{ $package->assignment_count }}回</span></div>
                        </a>
                    @empty
                        <div class="er-empty">条件に一致するルーティンはありません。</div>
                    @endforelse
                </div>
                <div class="er-column-footer">@include('admin.education.routines._compact-pagination', ['paginator' => $packages])</div>
            </section>

            <section class="er-panel er-column-panel er-selected-package">
                <div class="er-panel-title"><span>左で選択中のルーティン詳細</span></div>
                @if($selectedPackage)
                    <div class="er-package-detail-head">
                        <h2>{{ $selectedPackage->name }}</h2>
                        <p>{{ $selectedPackage->description ?: '説明は登録されていません。' }}</p>
                    </div>
                    <div class="er-detail-metrics">
                        <div><small>コード</small><strong>{{ $selectedPackage->package_code ?: '—' }}</strong></div>
                        <div><small>対象学年</small><strong>{{ $selectedPackage->target_grade ?: '—' }}</strong></div>
                        <div><small>対象レベル</small><strong>{{ $selectedPackage->target_level ?: '—' }}</strong></div>
                        <div><small>構成アイテム</small><strong>{{ $selectedPackage->items->count() }}件</strong></div>
                        <div><small>1日の目安</small><strong>約{{ $selectedPackage->total_minutes }}分</strong></div>
                        <div><small>推奨日数</small><strong>{{ $selectedPackage->recommended_days ?: '—' }}日</strong></div>
                    </div>
                    <div class="er-column-scroll er-package-detail-items">
                        <div class="er-section-heading"><strong>構成ルーティンアイテム</strong><span>{{ $selectedPackage->items->count() }}件</span></div>
                        @forelse($selectedPackage->items as $item)
                            <div class="er-item-v2"><div><strong>{{ $item->item_name ?: $item->master_name }}</strong><small>{{ $item->is_required ? '必須' : '任意' }}｜{{ $item->completion_type_name ?: '達成条件未設定' }}｜目標 {{ $item->target_value ?? '—' }}｜{{ $item->required_days ?: '—' }}日｜{{ $item->estimated_minutes ?: 0 }}分</small></div><span class="er-badge {{ in_array($item->learning_page_status, ['作成済み','created','completed','published'], true) ? 'green' : 'gray' }}">{{ in_array($item->learning_page_status, ['作成済み','created','completed','published'], true) ? '作成済' : '未作成' }}</span></div>
                        @empty
                            <div class="er-empty">構成アイテムはありません。</div>
                        @endforelse
                    </div>
                @else
                    <div class="er-empty large">左の一覧から内容を確認するルーティンを選択してください。</div>
                @endif
            </section>

            <section class="er-panel er-column-panel">
                <div class="er-panel-title"><span>左で選択中のルーティンを生徒に割り当てる</span></div>
                @if($selectedPackage)
                    <div class="er-assignment-target">
                        <strong>{{ $selectedPackage->name }}</strong>
                        <p>対象：{{ $selectedPackage->name }}｜{{ $selectedPackage->items->count() }}アイテム｜約{{ $selectedPackage->total_minutes }}分</p>
                        <button type="button" class="er-primary wide js-open-assign" data-package-id="{{ $selectedPackage->id }}">生徒へ割り当てる</button>
                    </div>
                    <div class="er-column-scroll">
                        <section class="er-assigned-section">
                            <div class="er-section-heading"><strong>現在の割当</strong><span>{{ $selectedPackage->current_students->count() }}名</span></div>
                            @forelse($selectedPackage->current_students->take(20) as $student)
                                <div class="er-assigned-student">
                                    <div class="er-assigned-student-main">
                                        <strong>{{ $student->student_name }}</strong>
                                        <small>{{ $student->student_code }}｜{{ $student->grade_name ?: '学年未設定' }}｜{{ $student->school_name ?: '教室未設定' }}</small>
                                    </div>
                                    <div class="er-assigned-student-actions">
                                        <span class="er-badge green">実施中</span>
                                    </div>
                                </div>
                            @empty
                                <div class="er-inline-empty">現在割り当てられている生徒はいません。</div>
                            @endforelse
                        </section>
                        <details class="er-assigned-section past">
                            <summary>過去の割当 {{ $selectedPackage->past_students->count() }}件</summary>
                            @foreach($selectedPackage->past_students->take(20) as $student)
                                <div class="er-assigned-student">
                                    <div class="er-assigned-student-main">
                                        <strong>{{ $student->student_name }}</strong>
                                        <small>{{ $student->student_code }}｜{{ $student->start_date ?: '開始日未設定' }}〜{{ $student->end_date ?: '期限なし' }}</small>
                                    </div>
                                    <div class="er-assigned-student-actions">
                                        <span class="er-badge gray">停止・終了</span>
                                    </div>
                                </div>
                            @endforeach
                        </details>
                    </div>
                @else
                    <div class="er-empty large">ルーティンを選択すると、割当状況を確認できます。</div>
                @endif
            </section>
        </div>
        @include('admin.education.routines._assign-modal')
    @endif

    @include('admin.education.routines._drawer')
</div>
    {{--
        layouts/admin.blade.php には @stack('scripts') が存在しないため、
        この画面で必要なJavaScriptはコンテンツ末尾から直接読み込む。
        deferを付け、HTML解析完了後に初期化する。
    --}}
    <script id="routinePackageData" type="application/json">{!! json_encode(collect($packages->items())->keyBy('id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script defer src="{{ asset('js/admin/education-routines.js') }}?v={{ file_exists(public_path('js/admin/education-routines.js')) ? filemtime(public_path('js/admin/education-routines.js')) : time() }}"></script>
@endsection
