@extends('layouts.admin')

@section('title', 'マスタ管理')

@section('content')
<div class="master-page"
     data-initial-master="{{ $initialMaster ?? request('master', 'enrollment_statuses') }}"
     data-master-list-url="{{ route('admin.system.master.list') }}"
     data-master-store-url="{{ route('admin.system.master.store') }}"
     data-master-update-url-base="{{ url('/admin/system/master') }}"
     data-master-reorder-url="{{ route('admin.system.master.reorder') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header">
        <div>
            <h1>{{ $pageTitle ?? 'マスタ管理' }}</h1>
            <p>{{ $pageDescription ?? '支払方法・雇用形態・イベントカテゴリなど、各種マスタを管理します。' }}</p>
        </div>

        <button type="button" class="master-primary-button" id="masterAddButton">
            <i class="fas fa-plus"></i>
            マスタ値を追加
        </button>
    </div>

    @if (($initialMaster ?? null) === 'notification_masters')
        <div class="master-layout master-layout-single">
    @else
        <div class="master-layout">
    @endif
        @if (($initialMaster ?? null) !== 'notification_masters')
            <aside class="master-sidebar">
            <div class="master-sidebar-title">マスタ一覧</div>

            @if (($initialMaster ?? null) === 'notification_masters')
                <div class="master-category">
                    <div class="master-category-title">連絡・通知</div>
                    <button class="master-menu active" data-master="notification_masters">
                        通知種類
                    </button>
                </div>
            @else
                <div class="master-category">
                    <div class="master-category-title">組織・人</div>
                    <button class="master-menu active" data-master="enrollment_statuses">入会状態</button>
                    <button class="master-menu" data-master="employment_types">雇用形態</button>
                    <button class="master-menu" data-master="teacher_statuses">講師状態</button>
                    <button class="master-menu" data-master="grades">学年</button>
                </div>

                <div class="master-category">
                    <div class="master-category-title">授業・学習</div>
                    <button class="master-menu" data-master="lesson_types">授業種別</button>
                    <button class="master-menu" data-master="learning_plan_types">学習計画種別</button>
                    <button class="master-menu" data-master="learning_material_categories">教材カテゴリ</button>
                    <button class="master-menu" data-master="routine_completion_types">達成判定種別</button>
                    <button class="master-menu" data-master="qualifications">資格種別</button>
                </div>

                <div class="master-category">
                    <div class="master-category-title">連絡・通知</div>
                    <button class="master-menu" data-master="note_types">メモ種別</button>
                    <button class="master-menu" data-master="contact_types">連絡種別</button>
                    <button class="master-menu" data-master="contact_statuses">対応状況</button>
                    <button class="master-menu" data-master="notification_masters">通知種類</button>
                </div>

                <div class="master-category">
                    <div class="master-category-title">会計</div>
                    <button class="master-menu" data-master="payment_methods">支払方法</button>
                    <button class="master-menu" data-master="discounts">割引種別</button>
                </div>

                <div class="master-category">
                    <div class="master-category-title">イベント</div>
                    <button class="master-menu" data-master="event_categories">イベントカテゴリ</button>
                    <button class="master-menu" data-master="event_statuses">イベント状態</button>
                    <button class="master-menu" data-master="event_rewards">イベント報酬</button>
                </div>

                <div class="master-category">
                    <div class="master-category-title">わくわく</div>
                    <button class="master-menu" data-master="badge_categories">バッジカテゴリ</button>
                    <button class="master-menu" data-master="reward_categories">景品カテゴリ</button>
                    <button class="master-menu" data-master="shop_categories">商品カテゴリ</button>
                </div>
            @endif
            </aside>
        @endif

        <main class="master-main">

            <section class="master-summary-card">
                <div>
                    <div class="master-current-label">選択中のマスタ</div>
                    <h2 id="masterTitle">入会状態</h2>
                    <p id="masterDescription">生徒の入会・休会・退会などの状態を管理します。</p>
                </div>

                <div class="master-summary-stats">
                    <div>
                        <span id="masterTotalCount">0</span>
                        <small>登録数</small>
                    </div>
                    <div>
                        <span id="masterActiveCount">0</span>
                        <small>有効</small>
                    </div>
                    <div>
                        <span id="masterInactiveCount">0</span>
                        <small>無効</small>
                    </div>
                </div>
            </section>

            <section class="master-toolbar">
                <div class="master-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="masterSearchInput" placeholder="コード・名称で検索">
                </div>

                <div class="master-filter-group">
                    <select id="masterStatusFilter">
                        <option value="all">すべて表示</option>
                        <option value="active">有効のみ</option>
                        <option value="inactive">無効のみ</option>
                    </select>
                </div>

                <button type="button" class="master-secondary-button" id="masterSeederButton">
                    <i class="fas fa-code"></i>
                    Seeder出力
                </button>
            </section>

            <section class="master-table-card">
                <table class="master-table">
                    <thead>
                        <tr id="masterTableHead">
                            <th>読み込み中...</th>
                        </tr>
                    </thead>
                    <tbody id="masterTableBody">
                        <tr>
                            <td colspan="6">読み込み中...</td>
                        </tr>
                    </tbody>
                </table>
            </section>

        </main>
    </div>
</div>

<div class="master-modal" id="masterModal">
    <div class="master-modal-content">
        <div class="master-modal-header">
            <h3 id="masterModalTitle">マスタ値を追加</h3>
            <button type="button" class="master-modal-close" id="masterModalClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="master-form">
            <label>
                コード
                <input type="text" id="masterCodeInput" placeholder="例：ACTIVE">
            </label>

            <label>
                名称
                <input type="text" id="masterNameInput" placeholder="例：在籍中">
            </label>

            <label>
                説明
                <textarea id="masterDescriptionInput" rows="3" placeholder="このマスタ値の説明を入力"></textarea>
            </label>

            <label>
                表示順
                <input type="number" id="masterSortInput" min="1" value="1">
            </label>

            <label class="master-checkbox">
                <input type="checkbox" id="masterActiveInput" checked>
                有効にする
            </label>
        </div>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="masterModalCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="masterModalSave">保存</button>
        </div>
    </div>
</div>

<div class="master-modal" id="masterSeederModal">
    <div class="master-modal-content master-seeder-modal">
        <div class="master-modal-header">
            <h3>Seeder出力</h3>
            <button type="button" class="master-modal-close" id="masterSeederClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <textarea id="masterSeederOutput" class="master-seeder-output" readonly></textarea>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="masterSeederCopy">コピー</button>
        </div>
    </div>
</div>
@endsection