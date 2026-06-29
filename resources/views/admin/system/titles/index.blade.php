@extends('layouts.admin')

@section('title', '称号管理')

@section('content')
<div class="title-page title-management-page"
     data-list-url="{{ route('admin.system.titles.list') }}"
     data-store-url="{{ route('admin.system.titles.store') }}"
     data-update-url-base="{{ url('/admin/system/titles') }}"
     data-reorder-url="{{ route('admin.system.titles.reorder') }}"
     data-duplicate-url-base="{{ url('/admin/system/titles') }}"
     data-deactivate-url-base="{{ url('/admin/system/titles') }}"
     data-delete-url-base="{{ url('/admin/system/titles') }}"
     data-bulk-deactivate-url="{{ route('admin.system.titles.bulk-deactivate') }}"
     data-bulk-delete-url="{{ route('admin.system.titles.bulk-destroy') }}"
     data-event-search-url="{{ route('admin.system.titles.events.search') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header">
        <div>
            <h1>称号管理</h1>
            <p>称号・レア度・表示順を管理します。</p>
        </div>

        <button type="button" class="master-primary-button" id="titleAddButton">
            <i class="fas fa-plus"></i>
            称号を追加
        </button>
    </div>

    <main class="master-main">
        <section class="master-summary-card">
            <div>
                <div class="master-current-label">称号管理</div>
                <h2>称号一覧</h2>
                <p>生徒が獲得・装備できる称号を管理します。</p>
            </div>

            <div class="master-summary-stats">
                <div><span id="titleTotalCount">0</span><small>登録数</small></div>
                <div><span id="titleActiveCount">0</span><small>有効</small></div>
                <div><span id="titleInactiveCount">0</span><small>無効</small></div>
            </div>
        </section>

        <section class="master-toolbar">
            <div class="master-search">
                <i class="fas fa-search"></i>
                <input type="text" id="titleSearchInput" placeholder="称号名・説明で検索">
            </div>

            <div class="master-filter-group">
                <div class="title-filter-search">
                    <input type="text"
                        id="titleTagFilterSearchInput"
                        placeholder="タグで検索">
                    <input type="hidden"
                        id="titleTagFilter"
                        value="all">

                </div>

                <div class="title-filter-search">
                    <input type="text"
                        id="titleEventFilterSearchInput"
                        placeholder="対象イベントで検索">

                </div>

                <select id="titleRarityFilter">
                    <option value="all">すべてのレア度</option>
                    @foreach($rarities as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select id="titleStatusFilter">
                    <option value="all">すべて表示</option>
                    <option value="active">有効のみ</option>
                    <option value="inactive">無効のみ</option>
                </select>
            </div>
        </section>

        <section class="master-table-card">
            <div class="master-table-actions">
                <button type="button" class="master-secondary-button" id="titleBulkDeactivateButton">
                    選択を無効化
                </button>

                <button type="button" class="master-secondary-button" id="titleBulkDeleteButton">
                    選択を削除
                </button>
            </div>

            <table class="master-table title-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="titleCheckAll"></th>
                        <th>↕</th>
                        <th>ID</th>
                        <th>称号名</th>
                        <th>画像</th>
                        <th>レア度</th>
                        <th>説明</th>
                        <th>対象イベント</th>
                        <th>タグ</th>
                        <th>Pt報酬</th>
                        <th>獲得者数</th>
                        <th>有効</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="titleTableBody">
                    <tr><td colspan="13">読み込み中...</td></tr>
                </tbody>
            </table>
        </section>
    </main>
</div>

<div class="master-modal" id="titleModal">
    <div class="master-modal-content">
        <div class="master-modal-header">
            <h3 id="titleModalTitle">称号を追加</h3>
            <button type="button" class="master-modal-close" id="titleModalClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="master-form">
            <input type="hidden" id="titleIdInput">

            <label>称号名<input type="text" id="titleNameInput"></label>
            <label>説明<textarea id="titleDescriptionInput" rows="3"></textarea></label>

            <label>
                レア度
                <select id="titleRarityInput">
                    @foreach($rarities as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                称号画像
                <input type="file" id="titleImageFileInput" accept=".jpg,.jpeg,.png,.webp">

                <div class="title-image-preview-wrapper">
                    <img id="titleImagePreview" class="title-image-preview" style="display:none;">
                </div>
            </label>

            <label>ポイント報酬<input type="number" id="titlePointRewardInput" min="0" value="0"></label>

            <div class="title-tags-editor">
                <div class="title-tags-editor-header">
                    <span>タグ</span>
                    <small>最大4つまで選択できます。</small>
                </div>

                <div class="title-tag-select-grid">
                    @for($i = 1; $i <= 4; $i++)
                        <label>
                            タグ{{ $i }}
                            <select class="titleTagInput">
                                <option value="">未選択</option>
                                @foreach($titleTags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endfor
                </div>
            </div>

            <div class="title-events-editor">
                <div class="title-events-editor-header">
                    <span>対象イベント</span>
                    <small>イベント管理実装後に検索連携します。</small>
                </div>

                <input type="text" id="titleEventSearchInput" placeholder="イベント名で検索">

                <div id="titleSelectedEvents" class="title-selected-events">
                    <span class="title-empty-text">対象イベントなし</span>
                </div>
            </div>

            <label class="master-checkbox">
                <input type="checkbox" id="titleActiveInput" checked>
                有効にする
            </label>
        </div>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="titleModalCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="titleModalSave">保存</button>
        </div>
    </div>
</div>
@endsection