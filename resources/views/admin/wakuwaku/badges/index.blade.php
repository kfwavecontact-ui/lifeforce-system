@extends('layouts.admin')

@section('title', 'バッジ一覧')

@section('content')
<div class="badge-page badge-list-page"
     data-list-url="{{ route('admin.wakuwaku.badges.list') }}"
     data-store-url="{{ route('admin.wakuwaku.badges.store') }}"
     data-update-url-base="{{ url('/admin/wakuwaku/badges') }}"
     data-reorder-url="{{ route('admin.wakuwaku.badges.reorder') }}"
     data-duplicate-url-base="{{ url('/admin/wakuwaku/badges') }}"
     data-toggle-active-url-base="{{ url('/admin/wakuwaku/badges') }}"
     data-delete-url-base="{{ url('/admin/wakuwaku/badges') }}"
     data-bulk-activate-url="{{ route('admin.wakuwaku.badges.bulk-activate') }}"
     data-bulk-deactivate-url="{{ route('admin.wakuwaku.badges.bulk-deactivate') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header badge-page-header">
        <div>
            <h1>バッジ一覧</h1>
            <p>バッジの作成・編集・獲得条件・付与設定を管理します。</p>
        </div>

        <button type="button" class="master-primary-button" id="badgeAddButton">
            <i class="fas fa-plus"></i>
            バッジを追加
        </button>
    </div>

    <main class="master-main badge-main">
        <section class="badge-summary-grid" aria-label="バッジ集計">
            <article class="badge-summary-item">
                <div class="badge-summary-icon"><i class="fas fa-medal"></i></div>
                <div><small>登録数</small><strong id="badgeTotalCount">0</strong><span>個</span><em id="badgeTotalTrend" class="badge-summary-trend">—</em></div>
            </article>
            <article class="badge-summary-item">
                <div class="badge-summary-icon"><i class="fas fa-user-check"></i></div>
                <div><small>現在保有人数</small><strong id="badgeCurrentHoldingCount">0</strong><span>人</span><em id="badgeHoldingTrend" class="badge-summary-trend">—</em></div>
            </article>
            <article class="badge-summary-item">
                <div class="badge-summary-icon"><i class="fas fa-award"></i></div>
                <div><small>今月獲得数</small><strong id="badgeMonthlyGrantCount">0</strong><span>件</span><em id="badgeGrantTrend" class="badge-summary-trend">—</em></div>
            </article>
            <article class="badge-summary-item">
                <div class="badge-summary-icon"><i class="fas fa-rotate-left"></i></div>
                <div><small>今月取り外し数</small><strong id="badgeMonthlyRemovalCount">0</strong><span>件</span><em id="badgeRemovalTrend" class="badge-summary-trend">—</em></div>
            </article>
        </section>

        <section class="master-toolbar badge-toolbar">
            <div class="badge-filter-row badge-filter-row-one">
                <div class="master-search badge-keyword-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="badgeSearchInput" placeholder="コード・バッジ名・説明で検索">
                </div>

                <div class="badge-filter-search">
                    <input type="text" id="badgeCategoryFilterSearchInput" placeholder="カテゴリ">
                    <input type="hidden" id="badgeCategoryFilter" value="all">
                    <div class="reward-search-list" id="badgeCategoryFilterList">
                        <button type="button" data-id="all">すべてのカテゴリ</button>
                        @foreach($categories as $category)
                            <button type="button" data-id="{{ $category->id }}">{{ $category->name }}</button>
                        @endforeach
                    </div>
                </div>
                <select id="badgeStatusFilter"><option value="all">状態：すべて</option><option value="active">有効のみ</option><option value="inactive">無効のみ</option></select>
                <select id="badgeGrantMethodFilter"><option value="all">付与方法：すべて</option><option value="auto">自動</option><option value="manual">手動</option><option value="both">自動・手動</option></select>
                <select id="badgeLevelFilter"><option value="all">難易度：すべて</option>@for($i = 1; $i <= 11; $i++)<option value="{{ $i }}">Lv{{ $i }}</option>@endfor</select>
            </div>

            <div class="badge-filter-row badge-filter-row-two">
                <div class="badge-filter-search">
                    <input type="text" id="badgeSeriesFilterSearchInput" placeholder="シリーズ">
                    <input type="hidden" id="badgeSeriesFilter" value="all">
                    <div class="reward-search-list" id="badgeSeriesFilterList">
                        <button type="button" data-id="all">すべてのシリーズ</button>
                        @foreach($series as $item)<button type="button" data-id="{{ $item->id }}">{{ $item->name }}</button>@endforeach
                    </div>
                </div>
                <select id="badgeLimitedFilter"><option value="all">種別：すべて</option><option value="normal">一般</option><option value="limited">限定</option></select>
                <select id="badgeRequirementStatusFilter"><option value="all">条件：すべて</option><option value="configured">設定済み</option><option value="unconfigured">未設定</option></select>
                <select id="badgeUsageStatusFilter"><option value="all">利用状況：すべて</option><option value="used">獲得履歴あり</option><option value="unused">未使用</option></select>
                <div class="badge-point-range" aria-label="獲得ポイント範囲">
                    <span class="badge-point-range-label">獲得Pt</span>
                    <div class="badge-point-range-fields">
                        <input type="number" id="badgePointMinFilter" class="badge-point-filter" min="0" placeholder="下限">
                        <span class="badge-point-range-separator">〜</span>
                        <input type="number" id="badgePointMaxFilter" class="badge-point-filter" min="0" placeholder="上限">
                    </div>
                </div>
                <select id="badgeSortFilter">
                    <option value="display_order">並び替え</option>
                    <option value="updated_desc">更新日時が新しい順</option>
                    <option value="name_asc">バッジ名順</option>
                    <option value="level_asc">レベルが低い順</option>
                    <option value="level_desc">レベルが高い順</option>
                    <option value="points_asc">獲得Ptが少ない順</option>
                    <option value="points_desc">獲得Ptが多い順</option>
                    <option value="holders_desc">現在保有者が多い順</option>
                    <option value="grants_desc">累計獲得数が多い順</option>
                </select>
            </div>

            <div class="badge-filter-row badge-filter-row-three">
                <button type="button" class="master-secondary-button badge-filter-clear" id="badgeFilterClearButton"><i class="fas fa-rotate-left"></i> 条件クリア</button>
            </div>
        </section>

        <section class="master-table-card badge-table-card">
            <div class="master-table-actions badge-table-actions">
                <div class="badge-bulk-actions">
                    <span id="badgeSelectedCount" class="badge-selected-count">0件選択中</span>
                    <button type="button" class="master-secondary-button" id="badgeBulkActivateButton">一括有効</button>
                    <button type="button" class="master-secondary-button" id="badgeBulkDeactivateButton">一括無効</button>
                </div>
                <button type="button" class="master-secondary-button badge-csv-button" id="badgeCsvExportButton"><i class="fas fa-file-csv"></i> CSV出力</button>
            </div>

            <div class="badge-table-scroll">
                <table class="master-table badge-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="badgeCheckAll"></th>
                            <th aria-label="並び替え">並び替え</th>
                            <th class="badge-sortable-header" data-sort-key="id">ID <span class="badge-sort-indicator">↕</span></th>
                            <th class="badge-sortable-header" data-sort-key="name">バッジ <span class="badge-sort-indicator">↕</span></th>
                            <th class="badge-sortable-header" data-sort-key="category">カテゴリ <span class="badge-sort-indicator">↕</span></th>
                            <th class="badge-sortable-header" data-sort-key="series">シリーズ <span class="badge-sort-indicator">↕</span></th>
                            <th class="badge-sortable-header" data-sort-key="level">難易度 <span class="badge-sort-indicator">↕</span></th>
                            <th>条件</th>
                            <th class="is-number badge-sortable-header" data-sort-key="points">獲得Pt <span class="badge-sort-indicator">↕</span></th>
                            <th class="is-number badge-sortable-header" data-sort-key="holders">保有 <span class="badge-sort-indicator">↕</span></th>
                            <th class="is-number badge-sortable-header" data-sort-key="grants">累計 <span class="badge-sort-indicator">↕</span></th>
                            <th class="badge-sortable-header" data-sort-key="status">状態 <span class="badge-sort-indicator">↕</span></th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="badgeTableBody">
                        <tr><td colspan="13" class="badge-table-message">読み込み中...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<div class="badge-condition-popover" id="badgeConditionPopover" hidden>
    <div class="badge-condition-popover-header">
        <strong>獲得条件</strong>
        <button type="button" id="badgeConditionPopoverClose" aria-label="閉じる"><i class="fas fa-times"></i></button>
    </div>
    <div id="badgeConditionPopoverBody"></div>
</div>
<div class="master-modal" id="badgeModal">
    <div class="master-modal-content">
        <div class="master-modal-header">
            <h3 id="badgeModalTitle">バッジを追加</h3>
            <button type="button" class="master-modal-close" id="badgeModalClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="badge-modal-tabs" role="tablist">
            <button type="button" class="badge-modal-tab is-active" data-tab="basic">基本情報</button>
            <button type="button" class="badge-modal-tab" data-tab="condition">獲得条件</button>
            <button type="button" class="badge-modal-tab" data-tab="publish">公開・通知</button>
        </div>

        <div class="master-form">
            <section class="badge-modal-panel is-active" data-panel="basic">
            <input type="hidden" id="badgeIdInput">

            <label>バッジ名<input type="text" id="badgeNameInput"></label>

            <label>
                バッジ画像
                <input type="file" id="badgeImageFileInput" accept=".jpg,.jpeg,.png,.webp">
                <input type="hidden" id="badgeImagePathInput">
                <div class="badge-image-preview-wrapper">
                    <img id="badgeImagePreview" class="badge-image-preview" style="display:none;">
                </div>
                <small class="master-form-help">画像選択後に、正方形へトリミングできます。</small>
            </label>

            <label>コード<input type="text" id="badgeCodeInput" readonly placeholder="保存時に自動採番されます"><small class="master-form-help">システム識別子のため編集できません。</small></label>

            <label>付与方法
                <select id="badgeGrantMethodInput">
                    <option value="auto">自動のみ</option>
                    <option value="manual">手動のみ</option>
                    <option value="both" selected>自動・手動</option>
                </select>
            </label>

            <label class="master-checkbox"><input type="checkbox" id="badgeLimitedInput">限定バッジにする</label>

            <label>
                カテゴリ
                <input type="text" id="badgeCategorySearchInput" placeholder="カテゴリ名で検索">
                <input type="hidden" id="badgeCategoryInput">
                <div class="reward-search-list" id="badgeCategoryList">
                    @foreach($categories as $category)
                        <button type="button" data-id="{{ $category->id }}">{{ $category->name }}</button>
                    @endforeach
                </div>
            </label>

            <label>
                シリーズ
                <input type="text" id="badgeSeriesSearchInput" placeholder="シリーズ名で検索">
                <input type="hidden" id="badgeSeriesInput">
                <div class="reward-search-list" id="badgeSeriesList">
                    <button type="button" data-id="">なし</button>
                    @foreach($series as $item)
                        <button type="button" data-id="{{ $item->id }}">{{ $item->name }}</button>
                    @endforeach
                </div>
                <small class="master-form-help">カテゴリに対応するシリーズを選択してください。</small>
            </label>

            <label>レベル
                <select id="badgeLevelInput">
                    @for($i = 1; $i <= 11; $i++)
                        <option value="{{ $i }}">Lv{{ $i }}</option>
                    @endfor
                </select>
            </label>

            </section>

            <section class="badge-modal-panel" data-panel="condition">
            <div class="badge-requirements-editor">
                <div class="badge-requirements-editor-header">
                    <span>条件グループ</span>
                    <button type="button" class="master-secondary-button" id="badgeRequirementAddButton">＋ 条件を追加</button>
                </div>
                <div class="badge-condition-operator">
                    <span>条件の組み合わせ</span>
                    <label><input type="radio" name="badgeConditionOperator" value="and" checked> AND（すべて満たす）</label>
                    <label><input type="radio" name="badgeConditionOperator" value="or"> OR（いずれかを満たす）</label>
                </div>
                <div id="badgeRequirementRows"></div>
                <p class="master-form-help">最大3つまで設定できます。</p>
            </div>

            <template id="badgeRequirementRowTemplate">
                <div class="badge-requirement-row">
                    <select class="badgeRequirementTypeInput">
                        <option value="">獲得条件を選択</option>
                        @foreach($requirementTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="badgeRequirementValueInput" placeholder="条件値・対象ID・回数など">
                    <button type="button" class="master-secondary-button badgeRequirementRemoveButton">削除</button>
                </div>
            </template>

            <label>獲得ポイント<input type="number" id="badgePointRewardInput" min="0" value="0"></label>
            </section>

            <section class="badge-modal-panel" data-panel="publish">
            <label>開始日<input type="date" id="badgeStartDateInput"></label>
            <label>終了日<input type="date" id="badgeEndDateInput"></label>
            <label>説明<textarea id="badgeDescriptionInput" rows="3"></textarea></label>
            <label>獲得メッセージ<textarea id="badgeAcquisitionMessageInput" rows="2"></textarea></label>
            <label class="master-checkbox"><input type="checkbox" id="badgeNotifyOnGrantInput" checked>獲得時に通知する</label>
            <label class="master-checkbox"><input type="checkbox" id="badgeAllowRegrantInput" checked>取り外し後の再付与を許可する</label>
            <label class="master-checkbox"><input type="checkbox" id="badgeActiveInput" checked>有効にする</label>
            </section>
        </div>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="badgeModalCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="badgeModalSave">保存</button>
        </div>
    </div>
</div>


<div class="master-modal badge-crop-modal" id="badgeCropModal" aria-hidden="true">
    <div class="master-modal-content badge-crop-modal-content">
        <div class="master-modal-header">
            <h3>バッジ画像をトリミング</h3>
            <button type="button" class="master-modal-close" id="badgeCropClose" aria-label="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="badge-crop-body">
            <div class="badge-crop-stage" id="badgeCropStage">
                <canvas id="badgeCropCanvas" width="360" height="360"></canvas>
            </div>
            <label class="badge-crop-zoom">拡大・縮小
                <input type="range" id="badgeCropZoom" min="1" max="3" step="0.01" value="1">
            </label>
            <p class="master-form-help">画像をドラッグして位置を調整してください。保存時は800×800pxのPNGに変換します。</p>
        </div>
        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="badgeCropCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="badgeCropApply">トリミングを適用</button>
        </div>
    </div>
</div>

<div id="badgeToast" class="badge-toast" role="status" aria-live="polite"></div>

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/badge-list.css') }}">
@endpush

<script src="{{ asset('js/admin/wakuwaku/badge-list.js') }}"></script>
@endsection
