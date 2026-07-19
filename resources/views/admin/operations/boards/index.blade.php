@extends('layouts.admin')

@section('title', '掲示板')

@section('content')
<div class="board-page">
    <div class="board-page-header">
        <div>
            <h1>掲示板</h1>
            <p>生徒・保護者へ公開する教室からのお知らせと個別の伝達事項を管理します。</p>
        </div>
        <button type="button" class="board-primary-button" data-board-open="create">
            <i class="fas fa-plus"></i> 新規掲示板登録
        </button>
    </div>

    @if (session('success'))
        <div class="board-alert board-alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="board-alert board-alert-error">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="board-search-card">
        <form id="boardListFilterForm" method="GET" action="{{ route('admin.operations.communication.boards.index') }}">
            <div class="board-search-grid">
                <label>キーワード
                    <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="タイトル・本文・カテゴリ">
                </label>
                <label>カテゴリ
                    <select name="category">
                        <option value="">すべて</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
                <label>公開対象
                    <select name="target_type">
                        <option value="">すべて</option>
                        <option value="all" @selected(($filters['target_type'] ?? '') === 'all')>全体</option>
                        <option value="school" @selected(($filters['target_type'] ?? '') === 'school')>教室</option>
                        <option value="grade" @selected(($filters['target_type'] ?? '') === 'grade')>学年</option>
                        <option value="course" @selected(($filters['target_type'] ?? '') === 'course')>コース</option>
                        <option value="student" @selected(($filters['target_type'] ?? '') === 'student')>生徒個別</option>
                    </select>
                </label>
                <label>状態
                    <select name="status">
                        <option value="">すべて</option>
                        <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>下書き</option>
                        <option value="published" @selected(($filters['status'] ?? '') === 'published')>公開</option>
                        <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>公開終了</option>
                    </select>
                </label>
                <label>公開開始日 From
                    <input type="date" name="publish_from" value="{{ $filters['publish_from'] ?? '' }}">
                </label>
                <label>公開終了日 To
                    <input type="date" name="publish_to" value="{{ $filters['publish_to'] ?? '' }}">
                </label>
            </div>
            <div class="board-search-footer">
                <label class="board-inline-check"><input type="checkbox" name="important" value="1" @checked(($filters['important'] ?? '') == 1)> 重要のみ</label>
                <label class="board-inline-check"><input type="checkbox" name="pinned" value="1" @checked(($filters['pinned'] ?? '') == 1)> ピン留めのみ</label>
                <div class="board-search-actions">
                    <button type="submit" class="board-search-button">検索</button>
                    <a href="{{ route('admin.operations.communication.boards.index') }}" class="board-clear-button">クリア</a>
                </div>
            </div>
        </form>
    </section>

    <section class="board-table-card">
        <div class="board-count">全{{ number_format($posts->total()) }}件</div>
        <div class="board-table-scroll">
            <table class="board-table">
                <thead>
                    @php
                        $sortUrl = function (string $column) use ($sort, $direction) {
                            $nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
                            return request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => null]);
                        };
                        $sortIcon = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
                    @endphp
                    <tr>
                        <th class="board-sticky-left board-id-column">
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('id') }}">ID <i class="fas {{ $sortIcon('id') }}"></i></a><button type="button" class="board-filter-trigger @if(($filters['id_min'] ?? '') !== '' || ($filters['id_max'] ?? '') !== '') active @endif" data-board-filter-trigger aria-label="IDを絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">ID</div>
                                <label>最小<input form="boardListFilterForm" type="number" name="id_min" value="{{ $filters['id_min'] ?? '' }}"></label>
                                <label>最大<input form="boardListFilterForm" type="number" name="id_max" value="{{ $filters['id_max'] ?? '' }}"></label>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th class="board-sticky-left board-title-column">
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('title') }}">タイトル <i class="fas {{ $sortIcon('title') }}"></i></a><button type="button" class="board-filter-trigger @if(($filters['title_filter'] ?? '') !== '') active @endif" data-board-filter-trigger aria-label="タイトルを絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">タイトル</div>
                                <label>文字を含む<input form="boardListFilterForm" type="text" name="title_filter" value="{{ $filters['title_filter'] ?? '' }}"></label>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th>
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('category') }}">カテゴリ <i class="fas {{ $sortIcon('category') }}"></i></a><button type="button" class="board-filter-trigger @if(!empty($filters['category_filter'])) active @endif" data-board-filter-trigger aria-label="カテゴリを絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">カテゴリ</div>
                                <div class="board-filter-option-list">@foreach($categories as $category)<label><input form="boardListFilterForm" type="checkbox" name="category_filter[]" value="{{ $category }}" @checked(in_array($category,(array)($filters['category_filter'] ?? []),true))>{{ $category }}</label>@endforeach</div>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th>
                            <div class="board-column-header"><span>公開対象</span><button type="button" class="board-filter-trigger @if(!empty($filters['target_type_filter'])) active @endif" data-board-filter-trigger aria-label="公開対象を絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">公開対象</div>
                                <div class="board-filter-option-list">
                                    @foreach(['all'=>'全体','school'=>'教室','grade'=>'学年','course'=>'コース','student'=>'生徒個別'] as $value => $label)<label><input form="boardListFilterForm" type="checkbox" name="target_type_filter[]" value="{{ $value }}" @checked(in_array($value,(array)($filters['target_type_filter'] ?? []),true))>{{ $label }}</label>@endforeach
                                </div>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th>対象人数</th>
                        <th>
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('publish_from') }}">公開期間 <i class="fas {{ $sortIcon('publish_from') }}"></i></a><button type="button" class="board-filter-trigger @if(($filters['publish_from_filter'] ?? '') !== '' || ($filters['publish_to_filter'] ?? '') !== '') active @endif" data-board-filter-trigger aria-label="公開期間を絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">公開期間</div>
                                <label>From<input form="boardListFilterForm" type="date" name="publish_from_filter" value="{{ $filters['publish_from_filter'] ?? '' }}"></label>
                                <label>To<input form="boardListFilterForm" type="date" name="publish_to_filter" value="{{ $filters['publish_to_filter'] ?? '' }}"></label>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th>
                            <div class="board-column-header"><span>重要</span><button type="button" class="board-filter-trigger @if(($filters['important_filter'] ?? '') == 1) active @endif" data-board-filter-trigger aria-label="重要表示を絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu><div class="board-filter-menu-title">重要</div><div class="board-filter-option-list"><label><input form="boardListFilterForm" type="checkbox" name="important_filter" value="1" @checked(($filters['important_filter'] ?? '') == 1)>重要のみ</label></div><div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div></div>
                        </th>
                        <th>
                            <div class="board-column-header"><span>ピン</span><button type="button" class="board-filter-trigger @if(($filters['pinned_filter'] ?? '') == 1) active @endif" data-board-filter-trigger aria-label="ピン留めを絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu><div class="board-filter-menu-title">ピン</div><div class="board-filter-option-list"><label><input form="boardListFilterForm" type="checkbox" name="pinned_filter" value="1" @checked(($filters['pinned_filter'] ?? '') == 1)>ピン留めのみ</label></div><div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div></div>
                        </th>
                        <th>閲覧数</th>
                        <th>既読率</th>
                        <th>
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('status') }}">状態 <i class="fas {{ $sortIcon('status') }}"></i></a><button type="button" class="board-filter-trigger @if(!empty($filters['status_filter'])) active @endif" data-board-filter-trigger aria-label="状態を絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">状態</div>
                                <div class="board-filter-option-list">@foreach(['draft'=>'下書き','published'=>'公開','closed'=>'公開終了'] as $value => $label)<label><input form="boardListFilterForm" type="checkbox" name="status_filter[]" value="{{ $value }}" @checked(in_array($value,(array)($filters['status_filter'] ?? []),true))>{{ $label }}</label>@endforeach</div>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th>
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('updated_at') }}">更新日 <i class="fas {{ $sortIcon('updated_at') }}"></i></a><button type="button" class="board-filter-trigger @if(($filters['updated_from'] ?? '') !== '' || ($filters['updated_to'] ?? '') !== '') active @endif" data-board-filter-trigger aria-label="更新日を絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">更新日</div>
                                <label>From<input form="boardListFilterForm" type="date" name="updated_from" value="{{ $filters['updated_from'] ?? '' }}"></label>
                                <label>To<input form="boardListFilterForm" type="date" name="updated_to" value="{{ $filters['updated_to'] ?? '' }}"></label>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th class="board-sticky-right board-operation-column">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posts as $post)
                        @php
                            $target = $post->targets->first();
                            $targetIds = $post->targets->pluck('target_id')->filter()->values();
                            $previewHeading = ($target?->target_type === 'student') ? 'あなたへのお知らせ' : '教室からのお知らせ';
                            $payload = [
                                'id' => $post->id,
                                'category' => $post->category,
                                'title' => $post->title,
                                'body' => $post->body,
                                'status' => $post->status,
                                'publish_from' => optional($post->publish_from)->format('Y-m-d\\TH:i'),
                                'publish_to' => optional($post->publish_to)->format('Y-m-d\\TH:i'),
                                'is_important' => $post->is_important,
                                'is_pinned' => $post->is_pinned,
                                'target_type' => $target?->target_type ?? 'all',
                                'target_ids' => $targetIds,
                                'target_label' => $post->target_label,
                                'target_count' => $post->target_count,
                                'reads_count' => $post->reads_count,
                                'read_rate' => $post->read_rate,
                                'attachments' => $post->attachment_items,
                                'created_at' => $post->created_at?->format('Y/m/d H:i'),
                                'updated_at' => $post->updated_at?->format('Y/m/d H:i'),
                                'preview_heading' => $previewHeading,
                            ];
                        @endphp
                        <tr>
                            <td class="board-sticky-left board-id-column">{{ $post->id }}</td>
                            <td class="board-sticky-left board-title-column">
                                <div class="board-title-cell" title="{{ $post->title }}">
                                    @if ($post->is_pinned)<i class="fas fa-thumbtack"></i>@endif
                                    {{ $post->title }}
                                </div>
                            </td>
                            <td>{{ $post->category }}</td>
                            <td><span class="board-target-badge">{{ $post->target_label }}</span></td>
                            <td class="board-number">{{ number_format($post->target_count) }}名</td>
                            <td class="board-period">
                                <div>{{ $post->publish_from?->format('Y/m/d H:i') ?? '未設定' }}</div>
                                <div>～ {{ $post->publish_to?->format('Y/m/d H:i') ?? '期限なし' }}</div>
                            </td>
                            <td class="board-center">@if($post->is_important)<span class="board-important">重要</span>@else - @endif</td>
                            <td class="board-center">@if($post->is_pinned)<i class="fas fa-thumbtack board-pin"></i>@else - @endif</td>
                            <td class="board-number">{{ number_format($post->reads_count) }}</td>
                            <td class="board-number">{{ number_format($post->read_rate, 1) }}%</td>
                            <td><span class="board-status board-status-{{ $post->status }}">{{ ['draft'=>'下書き','published'=>'公開','closed'=>'公開終了'][$post->status] ?? $post->status }}</span></td>
                            <td>{{ $post->updated_at?->format('Y/m/d H:i') }}</td>
                            <td class="board-sticky-right board-operation-column">
                                <div class="board-operation-wrap">
                                    <button type="button" class="board-operation-button" aria-expanded="false"><i class="fas fa-ellipsis-vertical"></i> 操作</button>
                                    <div class="board-operation-menu">
                                        <button type="button" data-board-action="preview" data-board='@json($payload)'><i class="fas fa-display"></i>表示イメージ</button>
                                        <button type="button" data-board-action="detail" data-board='@json($payload)'><i class="fas fa-file-lines"></i>詳細</button>
                                        <button type="button" data-board-action="edit" data-board='@json($payload)'><i class="fas fa-pen"></i>編集</button>
                                        <form method="POST" action="{{ route('admin.operations.communication.boards.duplicate', $post) }}">
                                            @csrf
                                            <button type="submit"><i class="fas fa-copy"></i>複製</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.operations.communication.boards.destroy', $post) }}" onsubmit="return confirm('この掲示板を削除しますか？');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="board-delete-action"><i class="fas fa-trash"></i>削除</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="board-empty">掲示板データがありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="board-pagination">{{ $posts->links() }}</div>
    </section>
</div>

<div class="board-modal" id="boardFormModal" aria-hidden="true">
    <div class="board-modal-backdrop" data-board-close></div>
    <div class="board-modal-dialog board-modal-wide">
        <div class="board-modal-header"><h2 id="boardFormTitle">新規掲示板登録</h2><button type="button" data-board-close>×</button></div>
        <form id="boardForm" method="POST" enctype="multipart/form-data" action="{{ route('admin.operations.communication.boards.store') }}">
            @csrf
            <input type="hidden" name="_method" id="boardFormMethod" value="POST">
            <div class="board-modal-body">
                <div class="board-form-grid">
                    <label>カテゴリ<span class="required">必須</span>
                        <select name="category" id="boardCategory" required>@foreach($categories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select>
                    </label>
                    <label>状態<span class="required">必須</span>
                        <select name="status" id="boardStatus" required><option value="draft">下書き</option><option value="published">公開</option><option value="closed">公開終了</option></select>
                    </label>

                    <label class="board-form-full">タイトル<span class="required">必須</span><input type="text" name="title" id="boardTitle" maxlength="255" required></label>
                    <label class="board-form-full">本文<span class="required">必須</span><textarea name="body" id="boardBody" rows="10" required></textarea></label>
                    <label class="board-form-full">添付ファイル（1ファイル20MBまで・合計最大10件）
                        <input type="file" name="attachments[]" id="boardAttachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                        <span class="board-attachment-help">複数ファイルをまとめて選択できます。後から追加選択しても、先に選んだファイルは保持されます。</span>
                    </label>
                    <div class="board-form-full board-selected-attachments" id="boardSelectedAttachments"></div>
                    <div class="board-form-full board-current-attachments" id="boardCurrentAttachments"></div>

                    <label>公開開始日時<input type="datetime-local" name="publish_from" id="boardPublishFrom"></label>
                    <label>公開終了日時<input type="datetime-local" name="publish_to" id="boardPublishTo"></label>

                    <div class="board-form-full board-check-row">
                        <label><input type="checkbox" name="is_important" id="boardImportant" value="1"> 重要表示</label>
                        <label><input type="checkbox" name="is_pinned" id="boardPinned" value="1"> ピン留め</label>
                    </div>

                    <div class="board-form-full">
                        <div class="board-field-title">公開対象<span class="required">必須</span></div>
                        <div class="board-target-options">
                            <label><input type="radio" name="target_type" value="all" checked>全体</label>
                            <label><input type="radio" name="target_type" value="school">教室</label>
                            <label><input type="radio" name="target_type" value="grade">学年</label>
                            <label><input type="radio" name="target_type" value="course">コース</label>
                            <label><input type="radio" name="target_type" value="student">生徒個別</label>
                        </div>
                        <div class="board-target-selects">
                            <select multiple name="target_ids[]" id="boardTargetsSchool" data-target-select="school">@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select>
                            <select multiple name="target_ids[]" id="boardTargetsGrade" data-target-select="grade">@foreach($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select>
                            <select multiple name="target_ids[]" id="boardTargetsCourse" data-target-select="course">@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select>
                            <select multiple name="target_ids[]" id="boardTargetsStudent" data-target-select="student">@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->student_code }} {{ $student->last_name }} {{ $student->first_name }}</option>@endforeach</select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="board-modal-footer"><button type="button" class="board-secondary-button" data-board-close>キャンセル</button><button type="submit" class="board-primary-button">保存</button></div>
        </form>
    </div>
</div>

<div class="board-modal" id="boardDetailModal" aria-hidden="true">
    <div class="board-modal-backdrop" data-board-close></div>
    <div class="board-modal-dialog">
        <div class="board-modal-header"><h2>掲示板詳細</h2><button type="button" data-board-close>×</button></div>
        <div class="board-modal-body" id="boardDetailContent"></div>
        <div class="board-modal-footer"><button type="button" class="board-secondary-button" data-board-close>閉じる</button></div>
    </div>
</div>

<div class="board-modal" id="boardPreviewModal" aria-hidden="true">
    <div class="board-modal-backdrop" data-board-close></div>
    <div class="board-modal-dialog board-preview-dialog">
        <div class="board-modal-header"><h2>掲示板表示イメージ</h2><button type="button" data-board-close>×</button></div>
        <div class="board-modal-body"><div id="boardPreviewContent" class="board-student-preview"></div></div>
        <div class="board-modal-footer"><button type="button" class="board-secondary-button" data-board-close>閉じる</button></div>
    </div>
</div>

<script>
window.boardRoutes = {
    store: @json(route('admin.operations.communication.boards.store')),
    update: @json(route('admin.operations.communication.boards.update', ['boardPost' => '__ID__']))
};
</script>
@endsection
