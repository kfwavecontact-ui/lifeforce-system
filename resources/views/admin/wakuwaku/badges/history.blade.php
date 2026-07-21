@extends('layouts.admin')

@section('title', 'バッジ獲得履歴')

@section('content')
<div class="badge-history-page"
     data-list-url="{{ route('admin.wakuwaku.badges.history.list') }}"
     data-detail-url-base="{{ url('/admin/wakuwaku/badges/history') }}"
     data-grant-url="{{ route('admin.wakuwaku.badges.history.grant') }}"
     data-student-lookup-url="{{ route('admin.wakuwaku.badges.history.lookup.students') }}"
     data-badge-lookup-url="{{ route('admin.wakuwaku.badges.history.lookup.badges') }}">
    <div class="master-page-header badge-history-header">
        <div>
            <h1>獲得履歴</h1>
            <p>生徒へのバッジ付与・取り外し・再付与の履歴を確認します。</p>
        </div>
        <button type="button" class="master-primary-button" id="manualGrantButton" >
            <i class="fas fa-plus"></i> 手動付与
        </button>
    </div>

    <main class="badge-history-main">
        <section class="badge-history-summary-grid" aria-label="獲得履歴集計">
            <article><span class="summary-icon"><i class="fas fa-list"></i></span><div><small>総付与記録</small><strong id="historyTotalGrants">0</strong><em>件</em></div></article>
            <article><span class="summary-icon"><i class="fas fa-user-check"></i></span><div><small>現在付与中</small><strong id="historyActiveHoldings">0</strong><em>件</em></div></article>
            <article><span class="summary-icon"><i class="fas fa-award"></i></span><div><small>今月獲得</small><strong id="historyMonthlyGrants">0</strong><em>件</em></div></article>
            <article><span class="summary-icon"><i class="fas fa-rotate-left"></i></span><div><small>今月取り外し</small><strong id="historyMonthlyRemovals">0</strong><em>件</em></div></article>
            <article><span class="summary-icon"><i class="fas fa-redo"></i></span><div><small>今月再付与</small><strong id="historyMonthlyRegrants">0</strong><em>件</em></div></article>
        </section>

        <section class="badge-history-filter-card">
            <div class="history-filter-grid">
                <label class="history-keyword"><span>検索</span><div><i class="fas fa-search"></i><input id="historyKeyword" type="text" placeholder="生徒名・生徒コード・バッジ名・理由"></div></label>
                <label><span>教室</span><select id="historySchool"><option value="all">すべて</option>@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></label>
                <label><span>バッジ</span><select id="historyBadge"><option value="all">すべて</option>@foreach($badges as $badge)<option value="{{ $badge->id }}">{{ $badge->name }}</option>@endforeach</select></label>
                <label><span>カテゴリ</span><select id="historyCategory"><option value="all">すべて</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
                <label><span>シリーズ</span><select id="historySeries"><option value="all">すべて</option>@foreach($series as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                <label><span>状態</span><select id="historyStatus"><option value="all">すべて</option><option value="active">付与中</option><option value="removed">取り外し済み</option></select></label>
                <label><span>付与方法</span><select id="historyMethod"><option value="all">すべて</option><option value="auto">自動</option><option value="manual">手動</option><option value="regrant">再付与</option></select></label>
                <label><span>獲得日（開始）</span><input id="historyDateFrom" type="date"></label>
                <label><span>獲得日（終了）</span><input id="historyDateTo" type="date"></label>
                <label><span>並び順</span><select id="historySort"><option value="acquired_desc">獲得日時が新しい順</option><option value="acquired_asc">獲得日時が古い順</option><option value="student_asc">生徒名 昇順</option><option value="student_desc">生徒名 降順</option><option value="badge_asc">バッジ名 昇順</option><option value="badge_desc">バッジ名 降順</option><option value="status_asc">状態 昇順</option><option value="status_desc">状態 降順</option></select></label>
            </div>
            <div class="history-filter-actions"><button type="button" id="historyResetButton"><i class="fas fa-rotate-left"></i> 条件クリア</button></div>
        </section>

        <section class="badge-history-table-card">
            <div class="history-table-toolbar"><span id="historyResultCount">0件</span><button type="button" id="historyCsvButton"><i class="fas fa-file-csv"></i> CSV出力</button></div>
            <div class="history-table-scroll">
                <table class="history-table">
                    <thead><tr>
                        <th><button data-sort="id">ID <span>↕</span></button></th>
                        <th><button data-sort="acquired">獲得日時 <span>↕</span></button></th>
                        <th><button data-sort="student">生徒 <span>↕</span></button></th>
                        <th>教室</th><th><button data-sort="badge">バッジ <span>↕</span></button></th><th><button data-sort="category">カテゴリ <span>↕</span></button></th><th><button data-sort="series">シリーズ <span>↕</span></button></th><th><button data-sort="method">付与方法 <span>↕</span></button></th><th><button data-sort="operator">付与者 <span>↕</span></button></th>
                        <th><button data-sort="status">状態 <span>↕</span></button></th><th>理由</th><th class="history-operation-column">操作</th>
                    </tr></thead>
                    <tbody id="historyTableBody"><tr><td colspan="12" class="history-loading">読み込み中...</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<div class="history-modal" id="historyDetailModal" aria-hidden="true">
    <div class="history-modal-backdrop" data-close-history-modal></div>
    <section class="history-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="historyDetailTitle">
        <header><div><h2 id="historyDetailTitle">獲得履歴詳細</h2><p id="historyDetailSubtitle"></p></div><button type="button" data-close-history-modal aria-label="閉じる">×</button></header>
        <div class="history-modal-body">
            <div class="history-detail-hero" id="historyDetailHero"></div><div class="history-detail-grid" id="historyDetailGrid"></div>
            <h3>操作履歴</h3><div class="history-timeline" id="historyTimeline"></div>
        </div>
        <footer><button type="button" data-close-history-modal>閉じる</button></footer>
    </section>
</div>

<div class="history-modal" id="manualGrantModal" aria-hidden="true">
    <div class="history-modal-backdrop" data-close-manual-modal></div>
    <section class="history-modal-dialog history-operation-dialog" role="dialog" aria-modal="true" aria-labelledby="manualGrantTitle">
        <header><div><h2 id="manualGrantTitle">バッジを手動付与</h2><p>対象生徒とバッジを選択してください。</p></div><button type="button" data-close-manual-modal aria-label="閉じる">×</button></header>
        <form id="manualGrantForm">
            <div class="history-modal-body history-form-grid">
                <label class="history-lookup-field">
                    <span>対象生徒 <b>必須</b></span>
                    <input type="hidden" name="student_id" id="manualStudentId">
                    <div class="history-lookup" data-lookup="student">
                        <div class="history-lookup-input-wrap"><i class="fas fa-search"></i><input type="search" id="manualStudentLookup" autocomplete="off" placeholder="生徒コード・氏名で検索"></div>
                        <div class="history-lookup-selected" id="manualStudentSelected" hidden></div>
                        <div class="history-lookup-results" id="manualStudentResults" hidden></div>
                    </div>
                </label>
                <label class="history-lookup-field">
                    <span>バッジ <b>必須</b></span>
                    <input type="hidden" name="badge_id" id="manualBadgeId">
                    <div class="history-lookup" data-lookup="badge">
                        <div class="history-lookup-input-wrap"><i class="fas fa-search"></i><input type="search" id="manualBadgeLookup" autocomplete="off" placeholder="バッジコード・名称で検索"></div>
                        <div class="history-lookup-selected" id="manualBadgeSelected" hidden></div>
                        <div class="history-lookup-results" id="manualBadgeResults" hidden></div>
                    </div>
                </label>
                <label class="history-form-wide"><span>獲得日時</span><div class="history-datetime-row"><input type="datetime-local" name="acquired_at" id="manualAcquiredAt"><button type="button" id="setCurrentAcquiredAt"><i class="fas fa-clock"></i> 現在時間を設定</button></div></label>
                <label class="history-form-wide"><span>付与理由 <b>必須</b></span><textarea name="reason" rows="4" maxlength="1000" required placeholder="手動付与した理由を入力してください。"></textarea></label>
                <label class="history-check"><input type="checkbox" name="notify" value="1"><span>生徒・保護者へ通知する</span></label>
                <label class="history-check"><input type="checkbox" name="is_displayed" value="1" checked><span>生徒画面に表示する</span></label>
                <div class="history-form-error history-form-wide" id="manualGrantError"></div>
            </div>
            <footer><button type="button" data-close-manual-modal>キャンセル</button><button type="submit" class="history-submit-button">付与する</button></footer>
        </form>
    </section>
</div>

<div class="history-modal" id="badgeOperationModal" aria-hidden="true">
    <div class="history-modal-backdrop" data-close-operation-modal></div>
    <section class="history-modal-dialog history-operation-dialog" role="dialog" aria-modal="true" aria-labelledby="badgeOperationTitle">
        <header><div><h2 id="badgeOperationTitle">バッジ操作</h2><p id="badgeOperationSubtitle"></p></div><button type="button" data-close-operation-modal aria-label="閉じる">×</button></header>
        <form id="badgeOperationForm">
            <input type="hidden" name="grant_id"><input type="hidden" name="operation_type">
            <div class="history-modal-body history-form-grid">
                <div class="history-operation-warning history-form-wide" id="badgeOperationWarning"><i class="fas fa-triangle-exclamation"></i><div><strong>この操作は履歴に記録されます。</strong><p>対象生徒とバッジを確認し、理由を入力してから実行してください。</p></div></div>
                <div id="removeFields" class="history-form-contents history-form-wide">
                    <label><span>取り外し理由 <b>必須</b></span><select name="reason_code"><option value="mistaken_grant">誤って付与した</option><option value="requirement_not_met">獲得条件を満たしていなかった</option><option value="registration_correction">登録内容の修正</option><option value="other">その他</option></select></label>
                    <label><span>理由の詳細</span><textarea name="reason_detail" rows="4" maxlength="1000" placeholder="必要に応じて詳細を入力してください。"></textarea></label>
                    <label><span>取り外し日時</span><input type="datetime-local" name="removed_at"></label>
                </div>
                <div id="regrantFields" class="history-form-contents history-form-wide">
                    <label><span>再付与理由 <b>必須</b></span><textarea name="reason" rows="4" maxlength="1000" placeholder="再付与する理由を入力してください。"></textarea></label>
                    <label><span>再付与日時</span><input type="datetime-local" name="acquired_at"></label>
                    <label class="history-check"><input type="checkbox" name="is_displayed" value="1" checked><span>生徒画面に表示する</span></label>
                </div>
                <label class="history-check history-form-wide"><input type="checkbox" name="notify" value="1"><span>生徒・保護者へ通知する</span></label>
                <label class="history-check history-form-wide history-confirm-check"><input type="checkbox" name="confirmed" value="1" required><span>対象と操作内容を確認しました</span></label><div class="history-form-error history-form-wide" id="badgeOperationError"></div>
            </div>
            <footer><button type="button" data-close-operation-modal>キャンセル</button><button type="submit" class="history-submit-button" id="badgeOperationSubmit">実行する</button></footer>
        </form>
    </section>
</div>

<div class="history-modal" id="historyCsvModal" aria-hidden="true">
    <div class="history-modal-backdrop" data-close-csv-modal></div>
    <section class="history-modal-dialog history-csv-dialog" role="dialog" aria-modal="true" aria-labelledby="historyCsvTitle">
        <header><div><h2 id="historyCsvTitle">CSV出力項目</h2><p>現在の検索条件と並び順で出力します。</p></div><button type="button" data-close-csv-modal aria-label="閉じる">×</button></header>
        <div class="history-modal-body">
            <div class="history-csv-actions"><button type="button" id="csvSelectAll">すべて選択</button><button type="button" id="csvClearAll">すべて解除</button></div>
            <div class="history-csv-grid" id="historyCsvColumns">
                @foreach([
                    'id'=>'ID','acquired_at'=>'獲得日時','student_code'=>'生徒コード','student_name'=>'生徒名','school_name'=>'教室',
                    'badge_code'=>'バッジコード','badge_name'=>'バッジ名','category_name'=>'カテゴリ','series_name'=>'シリーズ',
                    'grant_method_label'=>'付与方法','granted_by_name'=>'付与者','status_label'=>'状態','removed_at'=>'取り外し日時',
                    'removed_by_name'=>'取り外し担当','reason'=>'理由','is_displayed'=>'生徒画面表示',
                    'grant_notification_sent'=>'付与通知','removal_notification_sent'=>'取り外し通知'
                ] as $key => $label)
                    <label><input type="checkbox" value="{{ $key }}" checked><span>{{ $label }}</span></label>
                @endforeach
            </div>
        </div>
        <footer><button type="button" data-close-csv-modal>キャンセル</button><button type="button" class="history-submit-button" id="historyCsvExportConfirm"><i class="fas fa-file-csv"></i> 出力する</button></footer>
    </section>
</div>

<div class="history-toast" id="historyToast" role="status"></div>

<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/badge-history.css') }}">
<script src="{{ asset('js/admin/wakuwaku/badge-history.js') }}"></script>
@endsection
