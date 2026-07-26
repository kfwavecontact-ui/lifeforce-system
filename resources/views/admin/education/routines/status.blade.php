@extends('layouts.admin')

@section('title', '現在の取組')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin/education-routines.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/student-routine-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/routine-current-activity.css') }}">
@endpush

@section('content')
<div class="er-page er-current-activity er-current-activity-student-only" data-page="current-activity">
    <header class="er-head">
        <div>
            <h1>現在の取組</h1>
            <p>選択した教室の生徒に現在割り当てられている、ルーティン・ルーティンアイテムの取組状況を確認します。</p>
        </div>
    </header>

    @include('admin.education.routines._tabs')

    <form class="er-current-simple-search" method="get">
        <label>
            <span>確認する教室</span>
            <select name="school_id">
                @foreach($schools as $school)
                    <option value="{{ $school->id }}" @selected((int)$schoolId === (int)$school->id)>{{ $school->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="er-current-student-keyword">
            <span>生徒名・生徒ID</span>
            <input name="student_keyword" value="{{ request('student_keyword') }}" placeholder="生徒名・生徒IDを検索">
        </label>
        <button type="submit" class="er-search-button">検索</button>
        <a class="er-clear-button" href="{{ route('admin.education.routines.status', ['school_id' => $schoolId]) }}">クリア</a>
    </form>

    <div class="er-current-student-layout">
        <section class="er-panel er-current-subjects">
            <div class="er-panel-title">
                <span>確認する生徒を選択</span>
                <span>{{ number_format($subjects->total()) }}件</span>
            </div>
            <div class="er-column-scroll">
                @forelse($subjects as $subject)
                    <a class="er-current-subject {{ (int)$selectedId === (int)$subject->id ? 'selected' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['student_id' => $subject->id, 'page' => null]) }}">
                        <strong>{{ $subject->student_name }}</strong>
                        <small>{{ $subject->student_code }}｜{{ $subject->grade_name ?? '学年未設定' }}</small>
                        <div>
                            <span>ルーティン {{ $subject->routine_count }}件</span>
                            <span>アイテム {{ $subject->item_count }}件</span>
                        </div>
                        @if($subject->attention_count)
                            <em>要確認 {{ $subject->attention_count }}件</em>
                        @endif
                    </a>
                @empty
                    <div class="er-empty">選択した教室に対象生徒はいません。</div>
                @endforelse
            </div>
            @include('admin.education.routines._compact-pagination', ['paginator' => $subjects])
        </section>

        <section class="er-panel er-current-karte-panel">
            <div class="er-panel-title">
                <span>{{ $selected ? trim(($selected->last_name ?? '').' '.($selected->first_name ?? '')) . 'さんに現在割り当てられているルーティン・ルーティンアイテム' : '選択した生徒に現在割り当てられているルーティン・ルーティンアイテム' }}</span>
                @if($selected)
                    <a class="er-clear-button er-open-karte-button"
                       href="{{ route('admin.students.karte.show', ['student' => $selected->id, 'tab' => 'routine']) }}"
                       target="_blank"
                       rel="noopener noreferrer">生徒カルテを開く</a>
                @endif
            </div>
            @if($selected)
                <div class="er-profile er-current-assignment-heading">
                    <p>{{ $selected->student_code ?? '—' }}｜{{ $selected->grade_name ?? '学年未設定' }}｜{{ $selected->school_name ?? '教室未設定' }}</p>
                </div>
            @endif

            @if(!$selected)
                <div class="er-empty large">左の一覧から確認する生徒を選択してください。</div>
            @else
                @include('admin.partials.current-routine-table', [
                    'activeRoutines' => $activeRoutines,
                    'todayStatuses' => $todayStatuses,
                    'allStatuses' => $allStatuses,
                    'calendarStatuses' => $calendarStatuses,
                    'showActual' => false,
                    'showActions' => false,
                    'tableIdPrefix' => 'currentActivity',
                ])
            @endif
        </section>
    </div>
</div>
@endsection
