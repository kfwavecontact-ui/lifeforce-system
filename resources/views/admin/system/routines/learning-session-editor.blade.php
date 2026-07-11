@extends('layouts.admin')

@section('title', '学習日設定編集')

@section('content')
<div class="lle-builder-page lle-session-edit-page"
     data-session-edit-page
     data-save-url="{{ $builder['save_url'] }}"
     data-back-url="{{ $builder['back_url'] }}"
     data-csrf-token="{{ csrf_token() }}"
     data-session='@json($session)'>
    <div class="lle-builder-header">
        <div>
            <div class="lle-builder-breadcrumb">
                <a href="{{ route('admin.system.routines', ['tab' => 'items']) }}">ルーティン管理</a><span>›</span>
                <a href="{{ $builder['back_url'] }}">学習ページ開発</a><span>›</span><span>{{ $session['title'] }} 編集</span>
            </div>
            <h1>{{ $session['title'] }} 編集</h1>
            <p>この学習日の基本設定と、実施するステップを編集します。</p>
        </div>
        <div class="lle-builder-header-actions">
            <a class="lle-secondary-button" href="{{ $builder['back_url'] }}">1日あたりの学習設定へ戻る</a>
            <button type="button" class="lle-primary-button" data-session-save>保存</button>
        </div>
    </div>

    <div class="lle-builder-summary-card lle-session-summary-card">
        <div class="lle-summary-cell item-cell"><span class="lle-status-label">ルーティンアイテム</span><strong>{{ $builder['name'] }} <small>(ID:{{ $builder['id'] }})</small></strong></div>
        <div class="lle-summary-cell"><span class="lle-status-label">対象学年</span><b>{{ $builder['target_grade'] }}</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">難易度</span><b class="stars">{{ $builder['difficulty_stars'] }}</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">学習想定日数</span><b>{{ $builder['estimated_days'] }}日</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">1日の推奨学習時間</span><b>{{ $builder['daily_learning_minutes_label'] }}</b></div>
    </div>

    <div class="lle-session-edit-layout">
        <section class="lle-panel lle-session-settings-panel">
            <div class="lle-panel-heading"><div class="lle-heading-left"><span>01</span><div><h2>学習日設定</h2><p>タイトル・公開状態・開発メモを設定します。</p></div></div></div>
            <div class="lle-session-form-grid">
                <div class="lle-editor-field"><label>タイトル</label><input type="text" data-editor-title></div>
                <label class="lle-check-card lle-editor-check"><input type="checkbox" data-editor-show-subtitle><span>サブタイトルを生徒画面に表示する</span></label>
                <div class="lle-editor-field"><label>サブタイトル</label><input type="text" data-editor-subtitle placeholder="例：動画でコツを覚えよう"></div>
                <div class="lle-editor-field"><label>開発メモ</label><textarea data-editor-memo rows="5" placeholder="生徒には表示されません。"></textarea></div>
                <label class="lle-check-card lle-editor-check"><input type="checkbox" data-editor-published><span>この学習日を公開する</span></label>
                <label class="lle-check-card lle-editor-check"><input type="checkbox" data-editor-complete><span>開発完了として扱う</span></label>
            </div>
        </section>

        <section class="lle-panel lle-session-steps-panel">
            <div class="lle-panel-heading with-action">
                <div class="lle-heading-left"><span>02</span><div><h2>ステップ構成</h2><p>説明・動画・教材・問題などを順番に配置します。</p></div></div>
                <button type="button" class="lle-step-add-button" data-step-add-open>＋ ステップ追加</button>
            </div>
            <div class="lle-step-palette lle-session-step-palette" data-step-palette hidden>
                @foreach($stepTypes as $stepType)
                    <button type="button" data-step-add="{{ $stepType['key'] }}" data-step-label="{{ $stepType['label'] }}" data-step-description="{{ $stepType['description'] }}">
                        <strong>{{ $stepType['label'] }}</strong><span>{{ $stepType['description'] }}</span>
                    </button>
                @endforeach
            </div>
            <div class="lle-session-step-list" data-editor-step-list></div>
        </section>

        <section class="lle-panel lle-step-detail-editor" data-step-detail-editor hidden>
            <div class="lle-step-detail-heading"><div><span>選択中のステップ</span><h4 data-step-detail-title>ステップ編集</h4></div><button type="button" class="lle-secondary-button" data-step-detail-close>閉じる</button></div>
            <div data-step-detail-fields></div>
        </section>
    </div>
</div>
<script src="{{ asset('js/admin/lle-learning-builder.js') }}"></script>
@endsection
