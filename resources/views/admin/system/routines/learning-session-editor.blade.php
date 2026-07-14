@extends('layouts.admin')

@section('title', '学習日設定編集')

@section('content')
<div class="lle-builder-page lle-session-edit-page lle-live-editor-page"
     data-session-edit-page
     data-save-url="{{ $builder['save_url'] }}"
     data-back-url="{{ $builder['back_url'] }}"
     data-csrf-token="{{ csrf_token() }}"
     data-session='@json($session)'>
    <div class="lle-builder-header">
        <div>
            <div class="lle-builder-breadcrumb">
                <a href="{{ route('admin.system.routines', ['tab' => 'items']) }}">ルーティン管理</a><span>›</span>
                <a href="{{ $builder['back_url'] }}">1日あたりの学習設定</a><span>›</span><span>{{ $session['title'] }} 編集</span>
            </div>
            <h1><span data-page-heading>{{ $session['title'] }}</span> 編集</h1>
            <p>学習日の内容を編集しながら、生徒画面をリアルタイムで確認できます。</p>
        </div>
        <div class="lle-builder-header-actions-anchor" data-header-actions-anchor>
            <div class="lle-builder-header-actions" data-header-actions>
                <a class="lle-secondary-button" href="{{ $builder['back_url'] }}" data-session-back>一覧へ戻る</a>
                <span class="lle-save-state is-saved" data-save-state><i></i><span>保存済み</span></span>
                <button type="button" class="lle-primary-button" data-session-save>保存</button>
            </div>
        </div>
    </div>

    <div class="lle-builder-summary-card lle-session-summary-card">
        <div class="lle-summary-cell item-cell"><span class="lle-status-label">ルーティンアイテム</span><strong>{{ $builder['name'] }} <small>(ID:{{ $builder['id'] }})</small></strong></div>
        <div class="lle-summary-cell"><span class="lle-status-label">対象学年</span><b>{{ $builder['target_grade'] }}</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">難易度</span><b class="stars">{{ $builder['difficulty_stars'] }}</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">学習想定日数</span><b>{{ $builder['estimated_days'] }}日</b></div>
        <div class="lle-summary-cell"><span class="lle-status-label">1日の推奨学習時間</span><b>{{ $builder['daily_learning_minutes_label'] }}</b></div>
    </div>

    <section class="lle-panel lle-compact-session-settings">
        <div class="lle-compact-settings-heading">
            <div class="lle-live-heading-group">
                <span class="lle-live-number">01</span>
                <div><h2>設定</h2><p>この学習日の基本情報と学習画面の表示を設定します。</p></div>
            </div>
        </div>
        <div class="lle-compact-settings-grid">
            <div class="lle-editor-field lle-compact-title-field">
                <label>タイトル <em>必須</em></label>
                <input type="text" data-editor-title maxlength="255">
            </div>
            <div class="lle-editor-field lle-compact-subtitle-field">
                <label>サブタイトル</label>
                <input type="text" data-editor-subtitle maxlength="255" placeholder="例：動画でコツを覚えよう">
            </div>
            <label class="lle-check-card lle-editor-check lle-compact-check"><input type="checkbox" data-editor-show-subtitle><span>生徒画面に表示</span></label>
            <label class="lle-check-card lle-editor-check lle-compact-check"><input type="checkbox" data-editor-published><span>公開する</span></label>
            <label class="lle-check-card lle-editor-check lle-compact-check"><input type="checkbox" data-editor-complete><span>開発完了</span></label>
            <div class="lle-screen-background-settings">
                <div class="lle-background-settings-header">
                    <div>
                        <strong>学習画面の背景</strong>
                        <p>選択中のステップに表示する背景を設定します。</p>
                    </div>
                </div>

                <section class="lle-background-settings-section lle-background-source-section">
                    <div class="lle-background-section-heading">
                        <span>1</span>
                        <div><strong>背景を指定</strong><p>背景色、アップロード画像、または画像URLを設定します。</p></div>
                    </div>
                    <div class="lle-background-source-grid">
                        <label class="lle-background-color-field">背景色<input type="color" data-editor-background-color value="#f4f7fb"></label>
                        <div class="lle-background-upload-field">
                            <span>背景画像をアップロード</span>
                            <div class="lle-background-upload-controls">
                                <label class="lle-background-upload-button">画像を選択<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-editor-background-file></label>
                                <span class="lle-background-upload-name" data-editor-background-file-name>未選択</span>
                                <button type="button" class="lle-background-remove-button" data-editor-background-remove>画像を削除</button>
                            </div>
                        </div>
                        <label class="lle-background-image-url">背景画像URL<input type="url" data-editor-background-image placeholder="https://..."></label>
                    </div>
                </section>

                <section class="lle-background-settings-section lle-background-adjust-section">
                    <div class="lle-background-section-heading">
                        <span>2</span>
                        <div><strong>表示を調整</strong><p>画像の見せ方と透明度を設定します。</p></div>
                    </div>
                    <div class="lle-background-adjust-grid">
                        <label class="lle-background-display-field">背景画像の表示方法
                            <select data-editor-background-display>
                                <option value="cover">画面全体に表示（縦横比を保って拡大・一部切れる場合あり）</option>
                                <option value="contain">画像全体を表示（縦横比を保って収める・余白が出る場合あり）</option>
                                <option value="center">元の画像サイズで中央に1枚表示</option>
                                <option value="tile">元の画像サイズで縦横に並べて表示</option>
                                <option value="tile-x">元の画像サイズで横方向に並べて表示</option>
                                <option value="tile-y">元の画像サイズで縦方向に並べて表示</option>
                                <option value="width">横幅いっぱいに表示（縦横比を維持）</option>
                                <option value="height">高さいっぱいに表示（縦横比を維持）</option>
                                <option value="stretch">画面全体に合わせて伸縮（縦横比を維持しない）</option>
                            </select>
                        </label>
                        <label class="lle-background-opacity-field">背景画像の透明度
                            <div><input type="range" min="0" max="100" step="1" value="100" data-editor-background-opacity><output data-editor-background-opacity-value>100%</output></div>
                        </label>
                    </div>
                </section>

                <section class="lle-background-settings-section lle-background-copy-section">
                    <div class="lle-background-section-heading">
                        <span>3</span>
                        <div><strong>他のステップへコピー</strong><p>現在の背景設定を、選択したステップへ一度だけ複製します。</p></div>
                    </div>
                    <button type="button" class="lle-background-copy-open" data-background-copy-open>コピー先のステップを選択</button>
                </section>
            </div>
            <label class="lle-compact-note-field">
                <span>備考</span>
                <textarea data-editor-memo rows="1" placeholder="必要に応じて入力してください。生徒画面には表示されません。"></textarea>
            </label>
        </div>
    </section>

    <div class="lle-compact-editor-layout">
        <aside class="lle-compact-column lle-compact-steps-column">
            <section class="lle-panel lle-live-panel lle-compact-scroll-panel">
                <div class="lle-live-panel-heading with-action">
                    <div class="lle-live-heading-group"><div><h2>ステップ構成</h2><p>ドラッグ感覚で順番を管理</p></div></div>
                    <button type="button" class="lle-step-add-button" data-step-add-open>＋ 追加</button>
                </div>
                <div class="lle-step-palette lle-session-step-palette lle-component-step-palette" data-step-palette hidden>
                    <div class="lle-step-palette-section">
                        <strong class="lle-step-palette-title">よく使うステップ</strong>
                        <div class="lle-step-preset-grid">
                            @foreach($stepTypes as $stepType)
                                <button type="button" data-step-add="{{ $stepType['key'] }}" data-step-label="{{ $stepType['label'] }}" data-step-description="{{ $stepType['description'] }}">
                                    <strong>{{ $stepType['label'] }}</strong><span>{{ $stepType['description'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="lle-step-palette-section lle-custom-step-section">
                        <strong class="lle-step-palette-title">自由なステップを作成</strong>
                        <label>ステップ名<input type="text" maxlength="255" data-custom-step-name placeholder="例：振り返り、ヒント、チャレンジ"></label>
                        <span class="lle-icon-picker-label">アイコンを1つ選択</span>
                        @php
                            $lleStepIcons = [
                                'fa-shapes','fa-book-open','fa-book','fa-lightbulb','fa-brain','fa-puzzle-piece','fa-bullseye','fa-flag','fa-star','fa-trophy',
                                'fa-pencil-alt','fa-edit','fa-clipboard-list','fa-tasks','fa-question-circle','fa-info-circle','fa-search','fa-image','fa-camera','fa-video',
                                'fa-play','fa-volume-up','fa-music','fa-file','fa-file-pdf','fa-folder-open','fa-clock','fa-redo','fa-rocket','fa-check'
                            ];
                        @endphp
                        <div class="lle-step-icon-grid" role="radiogroup" aria-label="ステップアイコン">
                            @foreach($lleStepIcons as $index => $icon)
                                <button type="button" class="{{ $index === 0 ? 'is-selected' : '' }}" data-icon-choice="{{ $icon }}" title="{{ $icon }}" aria-label="{{ $icon }}">
                                    <i class="fas {{ $icon }}" aria-hidden="true"></i>
                                </button>
                            @endforeach
                        </div>
                        <button type="button" class="lle-custom-step-create" data-custom-step-create>この内容で追加</button>
                    </div>
                </div>
                <div class="lle-session-step-list lle-live-step-list" data-editor-step-list></div>
            </section>
        </aside>

        <main class="lle-compact-column lle-compact-detail-column">
            <section class="lle-panel lle-live-panel lle-live-detail-panel lle-compact-scroll-panel" data-step-detail-editor hidden>
                <div class="lle-live-panel-heading lle-property-heading">
                    <div class="lle-step-editor-heading-copy"><span class="lle-step-editor-heading-icon" data-step-detail-icon>•</span><div><h2 data-step-detail-title>ステップ編集</h2><p data-step-detail-subtitle>選択中のステップを設定します。</p></div></div>
                    <span class="lle-property-status">編集内容</span>
                </div>
                <div class="lle-live-detail-body" data-step-detail-fields></div>
            </section>
        </main>

        <aside class="lle-compact-column lle-live-preview-column">
            <section class="lle-panel lle-live-preview-panel lle-compact-preview-panel">
                <div class="lle-live-preview-heading">
                    <div><h2>生徒画面</h2><p>リアルタイムプレビュー</p></div>
                    <div class="lle-preview-heading-actions">
                        <button type="button" class="lle-exact-preview-button" data-exact-preview-open title="モーダルで実寸プレビューを開く">
                            <span aria-hidden="true">□</span> 正確なプレビュー
                        </button>
                    </div>
                </div>
                <div class="lle-student-preview-stage" data-preview-stage>
                    <div class="lle-student-preview-shell" data-preview-shell>
                        <header class="lle-student-preview-header">
                            <div><span data-preview-session-label>{{ $session['title'] }}</span><strong data-preview-session-subtitle></strong></div>
                            <div class="lle-student-preview-header-actions">
                                <span data-preview-progress-label>ステップ 0 / 0</span>
                                <div class="lle-preview-header-navigation" data-preview-header-navigation></div>
                            </div>
                        </header>
                        <div class="lle-student-preview-progress"><i data-preview-progress-bar></i></div>
                        <main class="lle-student-preview-content" data-preview-content></main>
                    </div>
                </div>
                <p class="lle-preview-note">プレビュー操作は保存されません。設定内容は「保存」を押すとDBへ反映されます。</p>
            </section>
        </aside>
    </div>


    <div class="lle-background-copy-modal" data-background-copy-modal hidden>
        <div class="lle-background-copy-backdrop" data-background-copy-close></div>
        <section class="lle-background-copy-dialog" role="dialog" aria-modal="true" aria-labelledby="lleBackgroundCopyTitle">
            <header class="lle-background-copy-header">
                <div>
                    <h2 id="lleBackgroundCopyTitle">背景設定のコピー先を選択</h2>
                    <p>現在のステップの背景色・背景画像・透明度・表示方法を、選択したステップへ一度だけコピーします。</p>
                </div>
                <button type="button" class="lle-background-copy-close" data-background-copy-close aria-label="閉じる">×</button>
            </header>
            <div class="lle-background-copy-tools">
                <label>ステップ名を検索<input type="search" data-background-copy-search placeholder="ステップ名で絞り込み"></label>
                <div>
                    <button type="button" data-background-copy-select-all>すべて選択</button>
                    <button type="button" data-background-copy-clear>選択解除</button>
                </div>
            </div>
            <div class="lle-background-copy-list" data-background-copy-list></div>
            <footer class="lle-background-copy-footer">
                <span data-background-copy-count>0件選択</span>
                <div>
                    <button type="button" class="lle-secondary-button" data-background-copy-close>キャンセル</button>
                    <button type="button" class="lle-primary-button" data-background-copy-apply>選択したステップへコピー</button>
                </div>
            </footer>
        </section>
    </div>


    <div class="lle-unsaved-leave-modal" data-unsaved-leave-modal hidden>
        <div class="lle-unsaved-leave-backdrop" data-unsaved-leave-no></div>
        <section class="lle-unsaved-leave-dialog" role="dialog" aria-modal="true" aria-labelledby="lleUnsavedLeaveTitle">
            <div class="lle-unsaved-leave-icon" aria-hidden="true">!</div>
            <div class="lle-unsaved-leave-copy">
                <h2 id="lleUnsavedLeaveTitle">保存せずに一覧へ戻りますか？</h2>
                <p>変更内容は保存されません。保存せずに一覧へ戻る場合は「はい」を選択してください。</p>
            </div>
            <div class="lle-unsaved-leave-actions">
                <button type="button" class="lle-secondary-button" data-unsaved-leave-no>いいえ</button>
                <button type="button" class="lle-danger-button" data-unsaved-leave-yes>はい（保存せずに戻る）</button>
            </div>
        </section>
    </div>

    <div class="lle-exact-preview-modal" data-exact-preview-modal hidden>
        <div class="lle-exact-preview-backdrop" data-exact-preview-close></div>
        <section class="lle-exact-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="lleExactPreviewTitle">
            <header class="lle-exact-preview-modal-header">
                <div>
                    <h2 id="lleExactPreviewTitle">正確なプレビュー</h2>
                    <p>実際の表示幅に近い状態で確認できます。設定画像が画面幅を超える場合は、プレビュー内を横スクロールしてください。</p>
                </div>
                <div class="lle-exact-preview-header-actions">
                    <div class="lle-exact-preview-device-switch" role="group" aria-label="正確なプレビューの表示幅">
                        <button type="button" class="is-active" data-exact-preview-device="desktop" aria-pressed="true">PC</button>
                        <button type="button" data-exact-preview-device="tablet" aria-pressed="false">Tablet</button>
                        <button type="button" data-exact-preview-device="mobile" aria-pressed="false">Mobile</button>
                    </div>
                    <button type="button" class="lle-exact-preview-close" data-exact-preview-close aria-label="正確なプレビューを閉じる">×</button>
                </div>
            </header>
            <div class="lle-exact-preview-modal-body">
                <div class="lle-exact-preview-viewport">
                    <section class="lle-exact-preview-shell" data-exact-preview-shell data-device="desktop">
                        <header class="lle-exact-preview-header">
                            <div>
                                <span data-exact-preview-session-label></span>
                                <strong data-exact-preview-session-subtitle hidden></strong>
                            </div>
                            <span data-exact-preview-progress-label></span>
                        </header>
                        <div class="lle-exact-preview-progress"><i data-exact-preview-progress-bar></i></div>
                        <main class="lle-exact-preview-content" data-exact-preview-content></main>
                    </section>
                </div>
            </div>
        </section>
    </div>
</div>
<link rel="stylesheet" href="{{ asset('css/admin/lle-shogi-mate.css') }}">
<script src="{{ asset('js/admin/lle-shogi-mate.js') }}"></script>
<script src="{{ asset('js/admin/lle-learning-builder.js') }}"></script>
@endsection
