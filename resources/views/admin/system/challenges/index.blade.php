@extends('layouts.admin')

@section('title', 'チャレンジ管理')

@section('content')
<div class="challenge-page challenge-management-page"
     data-list-url="{{ route('admin.system.challenges.list') }}"
     data-store-url="{{ route('admin.system.challenges.store') }}"
     data-update-url-base="{{ url('/admin/system/challenges') }}"
     data-reorder-url="{{ route('admin.system.challenges.reorder') }}"
     data-duplicate-url-base="{{ url('/admin/system/challenges') }}"
     data-deactivate-url-base="{{ url('/admin/system/challenges') }}"
     data-delete-url-base="{{ url('/admin/system/challenges') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header">
        <div>
            <h1>チャレンジ管理</h1>
            <p>チャレンジ・難易度・報酬を管理します。</p>
        </div>

        <button type="button" class="master-primary-button" id="challengeAddButton">
            <i class="fas fa-plus"></i>
            チャレンジを追加
        </button>
    </div>

    <main class="master-main">
        <section class="master-summary-card">
            <div>
                <div class="master-current-label">チャレンジ管理</div>
                <h2>チャレンジ一覧</h2>
                <p>チャレンジ・報酬・難易度を管理します。</p>
            </div>

            <div class="master-summary-stats">
                <div><span id="challengeTotalCount">0</span><small>登録数</small></div>
                <div><span id="challengeActiveCount">0</span><small>有効</small></div>
                <div><span id="challengeInactiveCount">0</span><small>無効</small></div>
            </div>
        </section>

        <section class="master-toolbar">
            <div class="master-search">
                <i class="fas fa-search"></i>
                <input type="text" id="challengeSearchInput" placeholder="コード・名称で検索">
            </div>

            <div class="master-filter-group">

                <div class="challenge-filter-search">
                    <input type="text"
                        id="challengeCategoryFilterSearchInput"
                        placeholder="カテゴリで検索">

                    <input type="hidden"
                        id="challengeCategoryFilter"
                        value="all">

                    <div class="reward-search-list" id="challengeCategoryFilterList">
                        <button type="button" data-id="all">すべてのカテゴリ</button>

                        @foreach($categories as $category)
                            <button type="button"
                                    data-id="{{ $category->id }}">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <select id="challengeDifficultyFilter">
                    <option value="all">すべての難易度</option>
                    <option value="1">★</option>
                    <option value="2">★★</option>
                    <option value="3">★★★</option>
                    <option value="4">★★★★</option>
                    <option value="5">★★★★★</option>
                </select>

                <div class="challenge-filter-search">
                    <input type="text"
                        id="challengeBadgeFilterSearchInput"
                        placeholder="バッジ報酬で検索">

                    <input type="hidden"
                        id="challengeBadgeFilter"
                        value="all">

                    <div class="reward-search-list" id="challengeBadgeFilterList">
                        <button type="button" data-id="all">すべてのバッジ</button>

                        @foreach($badges as $badge)
                            <button type="button"
                                    data-id="{{ $badge->id }}">
                                {{ $badge->name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="challenge-filter-search">
                    <input type="text"
                        id="challengeTitleFilterSearchInput"
                        placeholder="称号報酬で検索">

                    <input type="hidden"
                        id="challengeTitleFilter"
                        value="all">

                    <div class="reward-search-list" id="challengeTitleFilterList">
                        <button type="button" data-id="all">すべての称号</button>

                        @foreach($titles as $title)
                            <button type="button"
                                    data-id="{{ $title->id }}">
                                {{ $title->name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <select id="challengeStatusFilter">
                    <option value="all">すべて表示</option>
                    <option value="active">有効のみ</option>
                    <option value="inactive">無効のみ</option>
                </select>

            </div>
        </section>

        <section class="master-table-card">
            <table class="master-table challenge-table">
                <thead>
                    <tr>
                        <th>↕</th>
                        <th>ID</th>
                        <th>画像</th>
                        <th>カテゴリ</th>
                        <th>チャレンジ名</th>
                        <th>難易度</th>
                        <th>合格条件</th>
                        <th>バッジ報酬</th>
                        <th>称号報酬</th>
                        <th>Pt報酬</th>
                        <th>有効</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="challengeTableBody">
                    <tr><td colspan="12">読み込み中...</td></tr>
                </tbody>
            </table>
        </section>
    </main>
</div>

<div class="master-modal" id="challengeModal">
    <div class="master-modal-content">
        <div class="master-modal-header">
            <h3 id="challengeModalTitle">チャレンジを追加</h3>
            <button type="button" class="master-modal-close" id="challengeModalClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="master-form">
            <input type="hidden" id="challengeIdInput">

            <label>コード<input type="text" id="challengeCodeInput"></label>
            <label>チャレンジ名<input type="text" id="challengeNameInput"></label>

            <label>
                カテゴリ
                <input type="text" id="challengeCategorySearchInput" placeholder="カテゴリ名で検索">
                <input type="hidden" id="challengeCategoryInput">

                <div class="reward-search-list" id="challengeCategoryList">
                    @foreach($categories as $category)
                        <button type="button" data-id="{{ $category->id }}">{{ $category->name }}</button>
                    @endforeach
                </div>
            </label>

            <label>
                難易度
                <select id="challengeDifficultyInput">
                    <option value="1">★</option>
                    <option value="2">★★</option>
                    <option value="3">★★★</option>
                    <option value="4">★★★★</option>
                    <option value="5">★★★★★</option>
                </select>
            </label>

            <label>説明<textarea id="challengeDescriptionInput" rows="3"></textarea></label>
            <label>
                アイコン画像

                <input
                    type="file"
                    id="challengeIconFileInput"
                    accept=".png">

                <input
                    type="hidden"
                    id="challengeIconPathInput">

                <div class="challenge-icon-preview-wrapper">
                    <img
                        id="challengeIconPreview"
                        class="challenge-icon-preview"
                        style="display:none;">
                </div>
            </label>

            <label>
                合格判定タイプ
                <select id="challengeTypeInput">
                    <option value="score">点数判定</option>
                    <option value="teacher_approval">講師承認</option>
                    <option value="qualification">資格取得</option>
                    <option value="custom">その他</option>
                </select>
            </label>

            <label>合格条件説明<textarea id="requirementDescriptionInput" rows="3"></textarea></label>
            <label>満点・問題数<input type="number" id="maxScoreInput" min="0"></label>
            <label>合格点<input type="number" id="passingScoreInput" min="0"></label>

            <label>
                バッジ報酬
                <input type="text" id="badgeRewardSearchInput" placeholder="バッジ名で検索">
                <input type="hidden" id="badgeRewardInput">

                <div class="reward-search-list" id="badgeRewardList">
                    <button type="button" data-id="">なし</button>
                    @foreach($badges as $badge)
                        <button type="button" data-id="{{ $badge->id }}">{{ $badge->name }}</button>
                    @endforeach
                </div>
            </label>

            <label>
                称号報酬
                <input type="text" id="titleRewardSearchInput" placeholder="称号名で検索">
                <input type="hidden" id="titleRewardInput">

                <div class="reward-search-list" id="titleRewardList">
                    <button type="button" data-id="">なし</button>
                    @foreach($titles as $title)
                        <button type="button" data-id="{{ $title->id }}">{{ $title->name }}</button>
                    @endforeach
                </div>
            </label>

            <label>ポイント報酬<input type="number" id="pointRewardInput" min="0" value="0"></label>

            <label class="master-checkbox">
                <input type="checkbox" id="challengeActiveInput" checked>
                有効にする
            </label>
        </div>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="challengeModalCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="challengeModalSave">保存</button>
        </div>
    </div>
</div>
@endsection