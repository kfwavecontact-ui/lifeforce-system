@extends('layouts.admin')

@section('title', 'ポイント調整')

@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/point-adjustment.css') }}">

<div class="point-adjustment-page">
    <header class="point-adjustment-header">
        <div>
            <p class="point-adjustment-breadcrumb">わくわく ＞ ポイント ＞ ポイント調整</p>
            <h1>ポイント調整</h1>
            <p class="point-adjustment-description">生徒を選択し、管理上必要なポイントの加算・減算を行います。</p>
        </div>
        <div class="point-adjustment-header-actions">
            <a href="{{ route('admin.wakuwaku.points.index') }}" class="point-adjustment-secondary-button">ポイント残高</a>
            <a href="{{ route('admin.wakuwaku.points.history') }}" class="point-adjustment-secondary-button">ポイント履歴</a>
        </div>
    </header>

    @if (session('success'))
        <div class="point-adjustment-alert is-success" role="status">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="point-adjustment-layout">
        <section class="point-adjustment-student-card">
            <div class="point-adjustment-card-header">
                <div>
                    <h2>生徒を選択</h2>
                    <p>{{ number_format($students->total()) }}人</p>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.wakuwaku.points.adjustment') }}" class="point-adjustment-search-form">
                <label class="is-wide">
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
                <div class="point-adjustment-search-actions">
                    <button type="submit" class="point-adjustment-primary-button">検索</button>
                    <a href="{{ route('admin.wakuwaku.points.adjustment') }}" class="point-adjustment-clear-button">条件クリア</a>
                </div>
            </form>

            <div class="point-adjustment-student-list">
                @forelse ($students as $student)
                    @php $isSelected = $selectedStudent && (int) $selectedStudent->id === (int) $student->id; @endphp
                    <a href="{{ route('admin.wakuwaku.points.adjustment', array_filter([
                        'student_id' => $student->id,
                        'keyword' => $filters['keyword'] ?? null,
                        'grade_id' => $filters['grade_id'] ?? null,
                        'enrollment_status_id' => $filters['enrollment_status_id'] ?? null,
                        'page' => $students->currentPage(),
                    ])) }}" class="point-adjustment-student-row {{ $isSelected ? 'is-selected' : '' }}">
                        <span class="point-adjustment-select-mark"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="point-adjustment-student-main">
                            <strong>{{ $student->last_name }} {{ $student->first_name }}</strong>
                            <small>{{ $student->student_code }} ／ {{ $student->grade_name ?: '学年未設定' }}</small>
                        </span>
                        <span class="point-adjustment-status">{{ $student->enrollment_status_name ?: '—' }}</span>
                        <span class="point-adjustment-list-balance {{ $student->current_points < 0 ? 'is-negative' : '' }}">{{ number_format($student->current_points) }}<small> pt</small></span>
                    </a>
                @empty
                    <div class="point-adjustment-empty">条件に一致する生徒がいません。</div>
                @endforelse
            </div>

            <div class="point-adjustment-pagination">
                {{ $students->links() }}
            </div>
        </section>

        <section class="point-adjustment-form-card">
            @if ($selectedStudent)
                <div class="point-adjustment-selected-header">
                    <div>
                        <span class="point-adjustment-selected-label">選択中の生徒</span>
                        <h2>{{ $selectedStudent->last_name }} {{ $selectedStudent->first_name }}</h2>
                        <p>{{ $selectedStudent->student_code }} ／ {{ $selectedStudent->grade_name ?: '学年未設定' }} ／ {{ $selectedStudent->enrollment_status_name ?: '状態未設定' }}</p>
                    </div>
                    <div class="point-adjustment-current-balance">
                        <span>現在ポイント</span>
                        <strong id="currentPointValue" data-current-points="{{ (int) $selectedStudent->current_points }}" class="{{ $selectedStudent->current_points < 0 ? 'is-negative' : '' }}">
                            {{ number_format($selectedStudent->current_points) }}<small> pt</small>
                        </strong>
                    </div>
                </div>

                <div class="point-adjustment-stat-grid">
                    <div><span>累計獲得</span><strong>+{{ number_format($selectedStudent->total_earned_points) }} pt</strong></div>
                    <div><span>累計利用</span><strong>{{ number_format($selectedStudent->total_used_points) }} pt</strong></div>
                </div>

                <form method="POST" action="{{ route('admin.wakuwaku.points.adjustment.store') }}" class="point-adjustment-form" id="pointAdjustmentForm">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">

                    <fieldset>
                        <legend>調整区分 <span>必須</span></legend>
                        <div class="point-adjustment-type-options">
                            <label class="point-adjustment-type-option is-add">
                                <input type="radio" name="adjustment_type" value="add" @checked(old('adjustment_type', 'add') === 'add')>
                                <span><i class="fas fa-plus" aria-hidden="true"></i><strong>加算</strong><small>ポイントを追加します</small></span>
                            </label>
                            <label class="point-adjustment-type-option is-subtract">
                                <input type="radio" name="adjustment_type" value="subtract" @checked(old('adjustment_type') === 'subtract')>
                                <span><i class="fas fa-minus" aria-hidden="true"></i><strong>減算</strong><small>ポイントを差し引きます</small></span>
                            </label>
                        </div>
                        @error('adjustment_type')<p class="point-adjustment-error">{{ $message }}</p>@enderror
                    </fieldset>

                    <label class="point-adjustment-points-field">
                        <span>調整ポイント <em>必須</em></span>
                        <div><input type="number" name="points" id="adjustmentPoints" min="1" max="999999" step="1" value="{{ old('points') }}" placeholder="例：100" required><b>pt</b></div>
                        @error('points')<p class="point-adjustment-error">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span>調整理由 <em>必須</em></span>
                        <textarea name="reason" id="adjustmentReason" rows="4" maxlength="500" placeholder="調整が必要になった理由を具体的に入力してください。" required>{{ old('reason') }}</textarea>
                        <small class="point-adjustment-character-count"><span id="reasonCount">0</span> / 500文字</small>
                        @error('reason')<p class="point-adjustment-error">{{ $message }}</p>@enderror
                    </label>

                    <div class="point-adjustment-preview">
                        <div><span>現在ポイント</span><strong>{{ number_format($selectedStudent->current_points) }} pt</strong></div>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        <div><span>調整後ポイント</span><strong id="adjustedPointPreview">{{ number_format($selectedStudent->current_points) }} pt</strong></div>
                    </div>
                    <p class="point-adjustment-preview-error" id="adjustmentPreviewError" hidden>減算後の残高がマイナスになります。</p>

                    <div class="point-adjustment-form-note">
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <p>実行すると残高が更新され、ポイント履歴へ「手動ポイント調整」として記録されます。</p>
                    </div>

                    <div class="point-adjustment-submit-area">
                        <a href="{{ route('admin.wakuwaku.points.index') }}" class="point-adjustment-cancel-button">キャンセル</a>
                        <button type="submit" class="point-adjustment-submit-button" id="pointAdjustmentSubmit">調整を実行</button>
                    </div>
                </form>
            @else
                <div class="point-adjustment-no-selection">
                    <span><i class="fas fa-user-check" aria-hidden="true"></i></span>
                    <h2>調整する生徒を選択してください</h2>
                    <p>左側の生徒一覧から対象者を選ぶと、現在ポイントと調整フォームが表示されます。</p>
                </div>
            @endif
        </section>
    </div>
</div>

<script src="{{ asset('js/admin/wakuwaku/point-adjustment.js') }}"></script>
@endsection
