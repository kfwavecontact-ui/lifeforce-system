@extends('layouts.admin')

@section('title', '称号一覧')

@section('content')
<div class="title-page title-list-page"
     data-list-url="{{ route('admin.wakuwaku.titles.list') }}"
     data-store-url="{{ route('admin.wakuwaku.titles.store') }}"
     data-update-url-base="{{ url('/admin/wakuwaku/titles') }}"
     data-reorder-url="{{ route('admin.wakuwaku.titles.reorder') }}"
     data-duplicate-url-base="{{ url('/admin/wakuwaku/titles') }}"
     data-toggle-active-url-base="{{ url('/admin/wakuwaku/titles') }}"
     data-delete-url-base="{{ url('/admin/wakuwaku/titles') }}"
     data-bulk-activate-url="{{ route('admin.wakuwaku.titles.bulk-activate') }}"
     data-bulk-deactivate-url="{{ route('admin.wakuwaku.titles.bulk-deactivate') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header title-page-header">
        <div>
            <h1>称号一覧</h1>
            <p>称号の作成・編集・獲得条件・付与設定を管理します。</p>
        </div>

        <button type="button" class="master-primary-button" id="titleAddButton">
            <i class="fas fa-plus"></i>
            称号を追加
        </button>
    </div>

    <main class="master-main title-main">
        <section class="title-summary-grid" aria-label="称号集計">
            <article class="title-summary-item">
                <div class="title-summary-icon"><i class="fas fa-crown"></i></div>
                <div><small>登録数</small><strong id="titleTotalCount">0</strong><span>個</span><em id="titleTotalTrend" class="title-summary-trend">—</em></div>
            </article>
            <article class="title-summary-item">
                <div class="title-summary-icon"><i class="fas fa-user-check"></i></div>
                <div><small>現在保有人数</small><strong id="titleCurrentHoldingCount">0</strong><span>人</span><em id="titleHoldingTrend" class="title-summary-trend">—</em></div>
            </article>
            <article class="title-summary-item">
                <div class="title-summary-icon"><i class="fas fa-award"></i></div>
                <div><small>今月獲得数</small><strong id="titleMonthlyGrantCount">0</strong><span>件</span><em id="titleGrantTrend" class="title-summary-trend">—</em></div>
            </article>
            <article class="title-summary-item">
                <div class="title-summary-icon"><i class="fas fa-rotate-left"></i></div>
                <div><small>今月取り外し数</small><strong id="titleMonthlyRemovalCount">0</strong><span>件</span><em id="titleRemovalTrend" class="title-summary-trend">—</em></div>
            </article>
        </section>

        <section class="master-toolbar title-toolbar">
            <div class="title-filter-row title-filter-row-one">
                <div class="master-search title-keyword-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="titleSearchInput" placeholder="コード・称号名・説明で検索">
                </div>

                <div class="title-filter-search">
                    <input type="text" id="titleCategoryFilterSearchInput" placeholder="カテゴリ">
                    <input type="hidden" id="titleCategoryFilter" value="all">
                    <div class="reward-search-list" id="titleCategoryFilterList">
                        <button type="button" data-id="all">すべてのカテゴリ</button>
                        @foreach($categories as $category)
                            <button type="button" data-id="{{ $category->id }}">{{ $category->name }}</button>
                        @endforeach
                    </div>
                </div>
                <select id="titleStatusFilter"><option value="all">状態：すべて</option><option value="active">有効のみ</option><option value="inactive">無効のみ</option></select>
                <select id="titleGrantMethodFilter"><option value="all">付与方法：すべて</option><option value="auto">自動</option><option value="manual">手動</option><option value="both">自動・手動</option></select>
                <select id="titleLevelFilter"><option value="all">称号ランク：すべて</option>@for($i = 1; $i <= 5; $i++)<option value="{{ $i }}">★{{ $i }}</option>@endfor</select>
            </div>

            <div class="title-filter-row title-filter-row-two">
                <div class="title-filter-search">
                    <input type="text" id="titleSeriesFilterSearchInput" placeholder="シリーズ">
                    <input type="hidden" id="titleSeriesFilter" value="all">
                    <div class="reward-search-list" id="titleSeriesFilterList">
                        <button type="button" data-id="all">すべてのシリーズ</button>
                        @foreach($series as $item)<button type="button" data-id="{{ $item->id }}">{{ $item->name }}</button>@endforeach
                    </div>
                </div>
                <select id="titleLimitedFilter"><option value="all">種別：すべて</option><option value="normal">一般</option><option value="limited">限定</option></select>
                <select id="titleRequirementStatusFilter"><option value="all">条件：すべて</option><option value="configured">設定済み</option><option value="unconfigured">未設定</option></select>
                <select id="titleUsageStatusFilter"><option value="all">利用状況：すべて</option><option value="used">獲得履歴あり</option><option value="unused">未使用</option></select>
                <div class="title-point-range" aria-label="獲得ポイント範囲">
                    <span class="title-point-range-label">獲得Pt</span>
                    <div class="title-point-range-fields">
                        <input type="number" id="titlePointMinFilter" class="title-point-filter" min="0" placeholder="下限">
                        <span class="title-point-range-separator">〜</span>
                        <input type="number" id="titlePointMaxFilter" class="title-point-filter" min="0" placeholder="上限">
                    </div>
                </div>
                <select id="titleSortFilter">
                    <option value="display_order">並び替え</option>
                    <option value="updated_desc">更新日時が新しい順</option>
                    <option value="name_asc">称号名順</option>
                    <option value="level_asc">称号ランクが低い順</option>
                    <option value="level_desc">称号ランクが高い順</option>
                    <option value="points_asc">獲得Ptが少ない順</option>
                    <option value="points_desc">獲得Ptが多い順</option>
                    <option value="holders_desc">現在保有者が多い順</option>
                    <option value="grants_desc">累計獲得数が多い順</option>
                </select>
            </div>

            <div class="title-filter-row title-filter-row-three">
                <button type="button" class="master-secondary-button title-filter-clear" id="titleFilterClearButton"><i class="fas fa-rotate-left"></i> 条件クリア</button>
            </div>
        </section>

        <section class="master-table-card title-table-card">
            <div class="master-table-actions title-table-actions">
                <div class="title-bulk-actions">
                    <span id="titleSelectedCount" class="title-selected-count">0件選択中</span>
                    <button type="button" class="master-secondary-button" id="titleBulkActivateButton">一括有効</button>
                    <button type="button" class="master-secondary-button" id="titleBulkDeactivateButton">一括無効</button>
                </div>
                <button type="button" class="master-secondary-button title-csv-button" id="titleCsvExportButton"><i class="fas fa-file-csv"></i> CSV出力</button>
            </div>

            <div class="title-table-scroll">
                <table class="master-table title-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="titleCheckAll"></th>
                            <th aria-label="並び替え">並び替え</th>
                            <th class="title-sortable-header" data-sort-key="id">ID <span class="title-sort-indicator">↕</span></th>
                            <th class="title-sortable-header" data-sort-key="name">称号 <span class="title-sort-indicator">↕</span></th>
                            <th class="title-sortable-header" data-sort-key="category">カテゴリ <span class="title-sort-indicator">↕</span></th>
                            <th class="title-sortable-header" data-sort-key="series">シリーズ <span class="title-sort-indicator">↕</span></th>
                            <th class="title-sortable-header" data-sort-key="level">称号ランク <span class="title-sort-indicator">↕</span></th>
                            <th>条件</th>
                            <th class="is-number title-sortable-header" data-sort-key="points">獲得Pt <span class="title-sort-indicator">↕</span></th>
                            <th class="is-number title-sortable-header" data-sort-key="holders">保有 <span class="title-sort-indicator">↕</span></th>
                            <th class="is-number title-sortable-header" data-sort-key="grants">累計 <span class="title-sort-indicator">↕</span></th>
                            <th class="title-sortable-header" data-sort-key="status">状態 <span class="title-sort-indicator">↕</span></th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="titleTableBody">
                        <tr><td colspan="13" class="title-table-message">読み込み中...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<div class="title-condition-popover" id="titleConditionPopover" hidden>
    <div class="title-condition-popover-header">
        <strong>獲得条件</strong>
        <button type="button" id="titleConditionPopoverClose" aria-label="閉じる"><i class="fas fa-times"></i></button>
    </div>
    <div id="titleConditionPopoverBody"></div>
</div>
<div class="master-modal" id="titleModal">
    <div class="master-modal-content">
        <div class="master-modal-header">
            <h3 id="titleModalTitle">称号を追加</h3>
            <button type="button" class="master-modal-close" id="titleModalClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="title-modal-tabs" role="tablist">
            <button type="button" class="title-modal-tab is-active" data-tab="basic">基本情報</button>
            <button type="button" class="title-modal-tab" data-tab="condition">獲得条件</button>
            <button type="button" class="title-modal-tab" data-tab="publish">公開・通知</button>
        </div>

        <div class="master-form">
            <section class="title-modal-panel is-active" data-panel="basic">
            <input type="hidden" id="titleIdInput">

            <label>称号名<input type="text" id="titleNameInput"></label>

            <label>
                称号画像
                <input type="file" id="titleImageFileInput" accept=".jpg,.jpeg,.png,.webp">
                <input type="hidden" id="titleImagePathInput">
                <div class="title-image-preview-wrapper">
                    <img id="titleImagePreview" class="title-image-preview" style="display:none;">
                </div>
                <small class="master-form-help">画像選択後に、正方形へトリミングできます。</small>
            </label>

            <label>コード<input type="text" id="titleCodeInput" readonly placeholder="保存時に自動採番されます"><small class="master-form-help">システム識別子のため編集できません。</small></label>

            <label>付与方法
                <select id="titleGrantMethodInput">
                    <option value="auto">自動のみ</option>
                    <option value="manual">手動のみ</option>
                    <option value="both" selected>自動・手動</option>
                </select>
            </label>

            <label class="master-checkbox"><input type="checkbox" id="titleLimitedInput">限定称号にする</label>

            <label>
                カテゴリ
                <input type="text" id="titleCategorySearchInput" placeholder="カテゴリ名で検索">
                <input type="hidden" id="titleCategoryInput">
                <div class="reward-search-list" id="titleCategoryList">
                    @foreach($categories as $category)
                        <button type="button" data-id="{{ $category->id }}">{{ $category->name }}</button>
                    @endforeach
                </div>
            </label>

            <label>
                シリーズ
                <input type="text" id="titleSeriesSearchInput" placeholder="シリーズ名で検索">
                <input type="hidden" id="titleSeriesInput">
                <div class="reward-search-list" id="titleSeriesList">
                    <button type="button" data-id="">なし</button>
                    @foreach($series as $item)
                        <button type="button" data-id="{{ $item->id }}">{{ $item->name }}</button>
                    @endforeach
                </div>
                <small class="master-form-help">カテゴリに対応するシリーズを選択してください。</small>
            </label>

            <label>称号ランク
                <select id="titleLevelInput">
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}">★{{ $i }}</option>
                    @endfor
                </select>
            </label>

            </section>

            <section class="title-modal-panel" data-panel="condition">
            <div class="title-requirements-editor">
                <div class="title-requirements-editor-header">
                    <span>条件グループ</span>
                    <button type="button" class="master-secondary-button" id="titleRequirementAddButton">＋ 条件を追加</button>
                </div>
                <div class="title-condition-operator">
                    <span>条件の組み合わせ</span>
                    <label><input type="radio" name="titleConditionOperator" value="and" checked> AND（すべて満たす）</label>
                    <label><input type="radio" name="titleConditionOperator" value="or"> OR（いずれかを満たす）</label>
                </div>
                <div id="titleRequirementRows"></div>
                <p class="master-form-help">最大3つまで設定できます。回・日・点などの単位は入力せず、数値だけを入力してください。</p>
            </div>

            <template id="titleRequirementRowTemplate">
                <div class="title-requirement-row">
                    <select class="titleRequirementTypeInput">
                        <option value="">獲得条件を選択</option>
                        @foreach($requirementTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="titleRequirementValueInput" min="0" step="1" inputmode="numeric" placeholder="数値のみ（例：5）">
                    <button type="button" class="master-secondary-button titleRequirementRemoveButton">削除</button>
                </div>
            </template>

            <label>獲得ポイント<input type="number" id="titlePointRewardInput" min="0" value="0"></label>
            </section>

            <section class="title-modal-panel" data-panel="publish">
            <label>開始日<input type="date" id="titleStartDateInput"></label>
            <label>終了日<input type="date" id="titleEndDateInput"></label>
            <label>説明<textarea id="titleDescriptionInput" rows="3"></textarea></label>
            <label>獲得メッセージ<textarea id="titleAcquisitionMessageInput" rows="2"></textarea></label>
            <label class="master-checkbox"><input type="checkbox" id="titleNotifyOnGrantInput" checked>獲得時に通知する</label>
            <label class="master-checkbox"><input type="checkbox" id="titleAllowRegrantInput" checked>取り外し後の再付与を許可する</label>
            <label class="master-checkbox"><input type="checkbox" id="titleActiveInput" checked>有効にする</label>
            </section>
        </div>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="titleModalCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="titleModalSave">保存</button>
        </div>
    </div>
</div>


<div class="master-modal title-crop-modal" id="titleCropModal" aria-hidden="true">
    <div class="master-modal-content title-crop-modal-content">
        <div class="master-modal-header">
            <h3>称号画像をトリミング</h3>
            <button type="button" class="master-modal-close" id="titleCropClose" aria-label="閉じる"><i class="fas fa-times"></i></button>
        </div>
        <div class="title-crop-body">
            <div class="title-crop-stage" id="titleCropStage">
                <canvas id="titleCropCanvas" width="360" height="360"></canvas>
            </div>
            <label class="title-crop-zoom">拡大・縮小
                <input type="range" id="titleCropZoom" min="1" max="3" step="0.01" value="1">
            </label>
            <p class="master-form-help">画像をドラッグして位置を調整してください。保存時は800×800pxのPNGに変換します。</p>
        </div>
        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="titleCropCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="titleCropApply">トリミングを適用</button>
        </div>
    </div>
</div>

<div id="titleToast" class="title-toast" role="status" aria-live="polite"></div>

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/wakuwaku/title-list.css') }}">
@endpush

<script src="{{ asset('js/admin/wakuwaku/title-list.js') }}"></script>
@endsection
