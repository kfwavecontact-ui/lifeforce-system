@extends('layouts.admin')

@section('title', 'バッジ管理')

@section('content')
<div class="badge-page badge-management-page"
     data-list-url="{{ route('admin.system.badges.list') }}"
     data-store-url="{{ route('admin.system.badges.store') }}"
     data-update-url-base="{{ url('/admin/system/badges') }}"
     data-reorder-url="{{ route('admin.system.badges.reorder') }}"
     data-duplicate-url-base="{{ url('/admin/system/badges') }}"
     data-deactivate-url-base="{{ url('/admin/system/badges') }}"
     data-delete-url-base="{{ url('/admin/system/badges') }}"
     data-bulk-deactivate-url="{{ route('admin.system.badges.bulk-deactivate') }}"
     data-bulk-delete-url="{{ route('admin.system.badges.bulk-destroy') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header">
        <div>
            <h1>バッジ管理</h1>
            <p>バッジ・カテゴリ・シリーズ・獲得条件を管理します。</p>
        </div>

        <button type="button" class="master-primary-button" id="badgeAddButton">
            <i class="fas fa-plus"></i>
            バッジを追加
        </button>
    </div>

    <main class="master-main">
        <section class="master-summary-card">
            <div>
                <div class="master-current-label">バッジ管理</div>
                <h2>バッジ一覧</h2>
                <p>バッジ・レベル・獲得条件・ポイント報酬を管理します。</p>
            </div>

            <div class="master-summary-stats">
                <div><span id="badgeTotalCount">0</span><small>登録数</small></div>
                <div><span id="badgeActiveCount">0</span><small>有効</small></div>
                <div><span id="badgeInactiveCount">0</span><small>無効</small></div>
            </div>
        </section>

        <section class="master-toolbar">
            <div class="master-search">
                <i class="fas fa-search"></i>
                <input type="text" id="badgeSearchInput" placeholder="コード・バッジ名で検索">
            </div>

            <div class="master-filter-group">
                <div class="badge-filter-search">
                    <input type="text"
                           id="badgeCategoryFilterSearchInput"
                           placeholder="カテゴリで検索">

                    <input type="hidden"
                           id="badgeCategoryFilter"
                           value="all">

                    <div class="reward-search-list" id="badgeCategoryFilterList">
                        <button type="button" data-id="all">すべてのカテゴリ</button>

                        @foreach($categories as $category)
                            <button type="button"
                                    data-id="{{ $category->id }}">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="badge-filter-search">
                    <input type="text"
                           id="badgeSeriesFilterSearchInput"
                           placeholder="シリーズで検索">

                    <input type="hidden"
                           id="badgeSeriesFilter"
                           value="all">

                    <div class="reward-search-list" id="badgeSeriesFilterList">
                        <button type="button" data-id="all">すべてのシリーズ</button>

                        @foreach($series as $item)
                            <button type="button"
                                    data-id="{{ $item->id }}">
                                {{ $item->name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <select id="badgeLevelFilter">
                    <option value="all">すべてのレベル</option>
                    @for($i = 1; $i <= 11; $i++)
                        <option value="{{ $i }}">Lv{{ $i }}</option>
                    @endfor
                </select>

                <select id="badgeStatusFilter">
                    <option value="all">すべて表示</option>
                    <option value="active">有効のみ</option>
                    <option value="inactive">無効のみ</option>
                </select>
            </div>
        </section>

        <section class="master-table-card">
            <div class="master-table-actions">
                <button
                    type="button"
                    class="master-secondary-button"
                    id="badgeBulkDeactivateButton">
                    選択を無効化
                </button>

                <button
                    type="button"
                    class="master-secondary-button"
                    id="badgeBulkDeleteButton">
                    選択を削除
                </button>
            </div>

            <table class="master-table badge-table">
                <thead>
                    <tr>
                        <th>
                            <input
                                type="checkbox"
                                id="badgeCheckAll">
                        </th>
                        <th>↕</th>
                        <th>ID</th>
                        <th>バッジ名</th>
                        <th>画像</th>
                        <th>カテゴリ</th>
                        <th>シリーズ</th>
                        <th>レベル</th>
                        <th class="badge-limited-column">一般/限定</th>
                        <th>獲得条件</th>
                        <th>Pt報酬</th>
                        <th>有効</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="badgeTableBody">
                    <tr><td colspan="13">読み込み中...</td></tr>
                </tbody>
            </table>
        </section>
    </main>
</div>

<div class="master-modal" id="badgeModal">
    <div class="master-modal-content">
        <div class="master-modal-header">
            <h3 id="badgeModalTitle">バッジを追加</h3>
            <button type="button" class="master-modal-close" id="badgeModalClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="master-form">
            <input type="hidden" id="badgeIdInput">

            <label>コード<input type="text" id="badgeCodeInput"></label>
            <label>バッジ名<input type="text" id="badgeNameInput"></label>

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

                <small class="master-form-help">
                    シリーズが見つからない場合は「マスタ管理 ＞ バッジシリーズ」から登録してください。
                </small>
            </label>

            <label>
                レベル
                <select id="badgeLevelInput">
                    @for($i = 1; $i <= 11; $i++)
                        <option value="{{ $i }}">Lv{{ $i }}</option>
                    @endfor
                </select>
            </label>

            <label>説明<textarea id="badgeDescriptionInput" rows="3"></textarea></label>

            <label>
                バッジ画像

                <input
                    type="file"
                    id="badgeImageFileInput"
                    accept=".jpg,.jpeg,.png,.webp">

                <input
                    type="hidden"
                    id="badgeImagePathInput">

                <div class="badge-image-preview-wrapper">
                    <img
                        id="badgeImagePreview"
                        class="badge-image-preview"
                        style="display:none;">
                </div>
            </label>

            <div class="badge-requirements-editor">
                <div class="badge-requirements-editor-header">
                    <span>獲得条件</span>
                    <button type="button" class="master-secondary-button" id="badgeRequirementAddButton">
                        ＋ 条件を追加
                    </button>
                </div>

                <div id="badgeRequirementRows"></div>

                <p class="master-form-help">
                    獲得条件は最大3つまで設定できます。複数条件を設定した場合は、すべて満たした場合に獲得します。
                </p>
            </div>

            <template id="badgeRequirementRowTemplate">
                <div class="badge-requirement-row">
                    <select class="badgeRequirementTypeInput">
                        <option value="">獲得条件を選択</option>
                        @foreach($requirementTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>

                    <input type="text"
                        class="badgeRequirementValueInput"
                        placeholder="例：数字瞬間記憶 Lv1">

                    <button type="button" class="master-secondary-button badgeRequirementRemoveButton">
                        削除
                    </button>
                </div>
            </template>

            <label>ポイント報酬<input type="number" id="badgePointRewardInput" min="0" value="0"></label>

            <label class="master-checkbox">
                <input type="checkbox" id="badgeLimitedInput">
                限定バッジにする
            </label>

            <label>開始日<input type="date" id="badgeStartDateInput"></label>
            <label>終了日<input type="date" id="badgeEndDateInput"></label>

            <label class="master-checkbox">
                <input type="checkbox" id="badgeActiveInput" checked>
                有効にする
            </label>
        </div>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="badgeModalCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="badgeModalSave">保存</button>
        </div>
    </div>
</div>
@endsection