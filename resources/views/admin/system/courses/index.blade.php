@extends('layouts.admin')

@section('title', 'コース管理')

@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/course-management.css') }}">

<div class="course-page course-management-page"
     data-list-url="{{ route('admin.system.courses.list') }}"
     data-store-url="{{ route('admin.system.courses.store') }}"
     data-update-url-base="{{ url('/admin/system/courses') }}"
     data-reorder-url="{{ route('admin.system.courses.reorder') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header">
        <div>
            <h1>コース管理</h1>
            <p>コース・通塾種別・月額料金を管理します。</p>
        </div>

        <button type="button" class="master-primary-button" id="courseAddButton">
            <i class="fas fa-plus"></i>
            コースを追加
        </button>
    </div>

    <main class="master-main">
        <section class="master-summary-card">
            <div>
                <div class="master-current-label">設定</div>
                <h2>コース一覧</h2>
                <p>コースごとの通塾種別・料金・有効状態を管理します。</p>
            </div>

            <div class="master-summary-stats">
                <div><span id="courseTotalCount">0</span><small>登録数</small></div>
                <div><span id="courseActiveCount">0</span><small>有効</small></div>
                <div><span id="courseInactiveCount">0</span><small>無効</small></div>
            </div>
        </section>

        <section class="master-toolbar">
            <div class="master-search">
                <i class="fas fa-search"></i>
                <input type="text" id="courseSearchInput" placeholder="コード・名称で検索">
            </div>

            <div class="master-filter-group">
                <select id="courseStatusFilter">
                    <option value="all">すべて表示</option>
                    <option value="active">有効のみ</option>
                    <option value="inactive">無効のみ</option>
                </select>
            </div>
        </section>

        <section class="master-table-card">
            <table class="master-table course-table">
                <thead>
                    <tr>
                        <th class="master-drag-column">↕</th>
                        <th>ID</th>
                        <th>コード</th>
                        <th>コース名</th>
                        <th>説明</th>
                        <th>通塾種別</th>
                        <th>月額料金</th>
                        <th>表示順</th>
                        <th>おすすめ</th>
                        <th>有効</th>
                        <th>契約者数</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="courseTableBody">
                    <tr><td colspan="11">読み込み中...</td></tr>
                </tbody>
            </table>
        </section>
    </main>
</div>

<div class="master-modal" id="courseModal">
    <div class="master-modal-content">
        <div class="master-modal-header">
            <h3 id="courseModalTitle">コースを追加</h3>
            <button type="button" class="master-modal-close" id="courseModalClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="master-form">
            <input type="hidden" id="coursePriceIdInput">
            <input type="hidden" id="courseIdInput">

            <label>コースコード<input type="text" id="courseCodeInput" placeholder="例：BRAIN"></label>
            <label>コース名<input type="text" id="courseNameInput" placeholder="例：脳開発コース"></label>
            <label>
                通塾種別
                <select id="attendanceTypeInput">
                    <option value="週1">週1</option>
                    <option value="週2">週2</option>
                    <option value="週3">週3</option>
                    <option value="週4">週4</option>
                    <option value="週5">週5</option>
                    <option value="週6">週6</option>
                    <option value="フリー">フリー</option>
                </select>
            </label>
            <label>月額料金<input type="number" id="monthlyFeeInput" min="0" placeholder="例：18000"></label>
            <label>説明<textarea id="courseDescriptionInput" rows="3"></textarea></label>

            <label class="master-checkbox">
                <input type="checkbox" id="recommendedInput">
                おすすめにする
            </label>

            <label class="master-checkbox">
                <input type="checkbox" id="activeInput" checked>
                有効にする
            </label>
        </div>

        <div class="master-modal-footer">
            <button type="button" class="master-secondary-button" id="courseModalCancel">キャンセル</button>
            <button type="button" class="master-primary-button" id="courseModalSave">保存</button>
        </div>
    </div>
</div>

<script src="{{ asset('js/admin/course-management.js') }}"></script>
@endsection