@extends('layouts.admin')

@section('title', '学習ページ開発')

@section('content')
@php
    $statusLabels = ['未作成' => 'not-started', '作成中' => 'drafting', '作成済' => 'done', '不要' => 'none'];
    $estimatedDays = max(1, (int) ($builder['estimated_days'] ?? 1));
@endphp

<div class="lle-builder-page lle-daily-builder-page"
     data-builder-list-page
     data-save-url="{{ $builder['save_url'] }}"
     data-session-edit-url-template="{{ $builder['session_edit_url_template'] }}"
     data-csrf-token="{{ csrf_token() }}"
     data-sessions='@json($dailySessions)'>
    <div class="lle-builder-header">
        <div>
            <div class="lle-builder-breadcrumb">
                <a href="{{ route('admin.system.routines', ['tab' => 'items']) }}">ルーティン管理</a>
                <span>›</span>
                <span>学習ページ開発</span>
            </div>
            <h1>学習ページ開発</h1>
            <p>ルーティンアイテムの1日あたりの学習設定を追加・並び替え・公開管理します。内容編集は各学習日の編集画面で行います。</p>
        </div>
        <div class="lle-builder-header-actions">
            <a class="lle-secondary-button" href="{{ route('admin.system.routines', ['tab' => 'items']) }}">一覧へ戻る</a>
            <button type="button" class="lle-primary-button" data-list-save>保存</button>
        </div>
    </div>

    <div class="lle-builder-summary-card lle-builder-summary-card-v2">
        <div class="lle-summary-cell item-cell">
            <span class="lle-status-label">ルーティンアイテム</span>
            <strong>{{ $builder['name'] }} <small>(ID:{{ $builder['id'] }})</small></strong>
        </div>
        <div class="lle-summary-cell status-cell">
            <span class="lle-status-label">開発状態</span>
            <b class="status {{ $statusLabels[$builder['learning_page_status']] ?? 'not-started' }}" data-summary-main-status>{{ $builder['learning_page_status'] }}</b>
        </div>
        <div class="lle-summary-cell"><span class="lle-status-label">対象学年</span><b>{{ $builder['target_grade'] }}</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">難易度</span><b class="stars">{{ $builder['difficulty_stars'] }}</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">学習想定日数</span><b>{{ $builder['estimated_days'] }}日</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">1日の推奨学習時間</span><b>{{ $builder['daily_learning_minutes_label'] }}</b></div>
        <label class="lle-summary-cell lle-summary-control-cell">
            <span class="lle-status-label">公開状態</span>
            <select data-publication-status>
                <option value="unpublished" {{ ($builder['publication_status'] ?? 'unpublished') === 'unpublished' ? 'selected' : '' }}>非公開</option>
                <option value="published" {{ ($builder['publication_status'] ?? '') === 'published' ? 'selected' : '' }}>公開</option>
                <option value="stopped" {{ ($builder['publication_status'] ?? '') === 'stopped' ? 'selected' : '' }}>公開停止</option>
            </select>
        </label>
        <div class="lle-summary-cell lle-summary-date-cell">
            <span class="lle-status-label">公開開始</span>
            <b class="lle-summary-date-value">{{ $builder['publish_start_at'] ?: '－' }}</b>
        </div>
        <div class="lle-summary-cell lle-summary-date-cell">
            <span class="lle-status-label">公開終了</span>
            <b class="lle-summary-date-value">{{ $builder['publish_end_at'] ?: '－' }}</b>
        </div>
    </div>

    <section class="lle-panel lle-daily-list-only-panel">
        <div class="lle-panel-heading with-action">
            <div class="lle-heading-left">
                <div><h2>ルーティンアイテムの1日あたりの学習設定</h2></div>
            </div>
            <button type="button" class="lle-step-add-button" data-session-add>＋ 学習日追加</button>
        </div>

        <div class="lle-daily-summary lle-daily-summary-compact" data-daily-summary>
            <div class="has-meter"><span>完成</span><strong data-summary-done>0 / {{ $estimatedDays }}</strong><small data-summary-done-percent>0%</small><i><em data-summary-done-bar style="width:0%"></em></i></div>
            <div class="has-meter"><span>公開</span><strong data-summary-published>0 / {{ $estimatedDays }}</strong><small data-summary-published-percent>0%</small><i><em data-summary-published-bar style="width:0%"></em></i></div>
            <div class="has-meter"><span>未着手</span><strong data-summary-empty>{{ $estimatedDays }}</strong><small data-summary-empty-percent>100%</small><i><em data-summary-empty-bar style="width:100%"></em></i></div>
        </div>

        <div class="lle-daily-table-wrap">
            <table class="lle-daily-table">
                <thead><tr><th>日</th><th>タイトル</th><th>サブタイトル</th><th>表示</th><th>Step</th><th>開発状況</th><th>公開</th><th>操作</th></tr></thead>
                <tbody data-daily-session-body></tbody>
            </table>
        </div>
        <p class="lle-daily-help">※追加・複製・並び替え・公開切替・削除は、この画面で保存してください。</p>
    </section>
</div>
<script src="{{ asset('js/admin/lle-learning-builder.js') }}"></script>
@endsection
