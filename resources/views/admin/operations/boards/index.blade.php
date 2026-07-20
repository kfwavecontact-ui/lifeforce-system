@extends('layouts.admin')

@section('title', '教室からのご連絡')

@section('content')
<div class="board-page">
    <div class="board-page-header">
        <div>
            <h1>教室からのご連絡</h1>
            <p>教室から生徒・保護者へ共有する連絡内容を、掲載・通知の方法で管理します。</p>
        </div>
        <button type="button" class="board-primary-button" data-board-open="create">
            <i class="fas fa-plus"></i> 新規ご連絡登録
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
                <label>共有区分
                    <select name="share_type">
                        <option value="">すべて</option>
                        <option value="all" @selected(($filters['share_type'] ?? '') === 'all')>全体共有</option>
                        <option value="individual" @selected(($filters['share_type'] ?? '') === 'individual')>個別共有</option>
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
                <label class="board-inline-check"><input type="checkbox" name="is_listed" value="1" @checked(($filters['is_listed'] ?? '') == 1)> 掲載あり</label>
                <label class="board-inline-check"><input type="checkbox" name="is_notified" value="1" @checked(($filters['is_notified'] ?? '') == 1)> 通知あり</label>
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
                        <th>
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('category') }}">カテゴリ <i class="fas {{ $sortIcon('category') }}"></i></a><button type="button" class="board-filter-trigger @if(!empty($filters['category_filter'])) active @endif" data-board-filter-trigger aria-label="カテゴリを絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">カテゴリ</div>
                                <div class="board-filter-option-list">@foreach($categories as $category)<label><input form="boardListFilterForm" type="checkbox" name="category_filter[]" value="{{ $category }}" @checked(in_array($category,(array)($filters['category_filter'] ?? []),true))>{{ $category }}</label>@endforeach</div>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th>
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('status') }}">状態 <i class="fas {{ $sortIcon('status') }}"></i></a><button type="button" class="board-filter-trigger @if(!empty($filters['status_filter'])) active @endif" data-board-filter-trigger aria-label="状態を絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">状態</div>
                                <div class="board-filter-option-list">@foreach(['draft'=>'下書き','published'=>'公開','closed'=>'公開終了'] as $value => $label)<label><input form="boardListFilterForm" type="checkbox" name="status_filter[]" value="{{ $value }}" @checked(in_array($value,(array)($filters['status_filter'] ?? []),true))>{{ $label }}</label>@endforeach</div>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th class="board-title-column">
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('title') }}">タイトル <i class="fas {{ $sortIcon('title') }}"></i></a><button type="button" class="board-filter-trigger @if(($filters['title_filter'] ?? '') !== '') active @endif" data-board-filter-trigger aria-label="タイトルを絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">タイトル</div>
                                <label>文字を含む<input form="boardListFilterForm" type="text" name="title_filter" value="{{ $filters['title_filter'] ?? '' }}"></label>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
                        <th>添付</th>
                        <th><div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('is_listed') }}">掲載 <i class="fas {{ $sortIcon('is_listed') }}"></i></a></div></th>
                        <th><div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('is_notified') }}">通知 <i class="fas {{ $sortIcon('is_notified') }}"></i></a></div></th>
                        <th><div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('notify_at') }}">通知日時 <i class="fas {{ $sortIcon('notify_at') }}"></i></a></div></th>
                        <th>対象人数</th>
                        <th><div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('share_type') }}">共有区分 <i class="fas {{ $sortIcon('share_type') }}"></i></a></div></th>
                        <th>
                            <div class="board-column-header"><a class="board-sort-link" href="{{ $sortUrl('target_type') }}">公開対象 <i class="fas {{ $sortIcon('target_type') }}"></i></a><button type="button" class="board-filter-trigger @if(!empty($filters['target_type_filter'])) active @endif" data-board-filter-trigger aria-label="公開対象を絞り込む"><i class="fas fa-filter"></i></button></div>
                            <div class="board-column-filter-menu" data-board-filter-menu>
                                <div class="board-filter-menu-title">公開対象</div>
                                <div class="board-filter-option-list">@foreach(['all'=>'全体','school'=>'教室','grade'=>'学年','course'=>'コース','student'=>'生徒個別'] as $value => $label)<label><input form="boardListFilterForm" type="checkbox" name="target_type_filter[]" value="{{ $value }}" @checked(in_array($value,(array)($filters['target_type_filter'] ?? []),true))>{{ $label }}</label>@endforeach</div>
                                <div class="board-filter-menu-actions"><button form="boardListFilterForm" type="submit">適用</button><button type="button" data-board-filter-clear>解除</button></div>
                            </div>
                        </th>
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
                        <th>確認状況</th>
                        <th>既読率</th>
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
                            $previewHeading = '教室からのご連絡';
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
                                'is_listed' => $post->is_listed,
                                'is_notified' => $post->is_notified,
                                'notify_at' => optional($post->notify_at)->format('Y-m-d\TH:i'),
                                'notified_at' => optional($post->notified_at)->format('Y-m-d\TH:i'),
                                'requires_confirmation' => $post->requires_confirmation,
                                'share_type' => $post->share_type,
                                'target_type' => $target?->target_type ?? 'all',
                                'target_ids' => $targetIds,
                                'target_label' => $post->target_label,
                                'target_count' => $post->target_count,
                                'reads_count' => $post->reads_count,
                                'read_rate' => $post->read_rate,
                                'confirmations_count' => $post->confirmations_count,
                                'confirmation_rate' => $post->confirmation_rate,
                                'attachments_count' => $post->attachments_count,
                                'attachments' => $post->attachment_items,
                                'created_at' => $post->created_at?->format('Y/m/d H:i'),
                                'updated_at' => $post->updated_at?->format('Y/m/d H:i'),
                                'preview_heading' => $previewHeading,
                            ];
                        @endphp
                        <tr>
                            <td class="board-sticky-left board-id-column">{{ $post->id }}</td>
                            <td>{{ $post->category }}</td>
                            <td><span class="board-status board-status-{{ $post->status }}">{{ ['draft'=>'下書き','published'=>'公開','closed'=>'公開終了'][$post->status] ?? $post->status }}</span></td>
                            <td class="board-title-column">
                                <div class="board-title-cell" title="{{ $post->title }}">
                                    {{ $post->title }}
                                </div>
                            </td>
                            <td class="board-center">@if($post->attachments_count > 0)<span class="board-attachment-count"><i class="fas fa-paperclip"></i>{{ number_format($post->attachments_count) }}</span>@else - @endif</td>
                            <td class="board-center">@if($post->is_listed)<span class="board-delivery-badge board-delivery-listed"><i class="fas fa-thumbtack"></i> 掲載</span>@else - @endif</td>
                            <td class="board-center">@if($post->is_notified)<span class="board-delivery-badge board-delivery-notified"><i class="fas fa-bell"></i> 通知</span>@else - @endif</td>
                            <td class="board-notify-date">
                                @if($post->is_notified)
                                    <div>{{ $post->notify_at?->format('Y/m/d H:i') ?? '即時' }}</div>
                                    <small>{{ $post->notified_at ? '通知済' : ($post->notify_at && $post->notify_at->isFuture() ? '予約' : '未通知') }}</small>
                                @else - @endif
                            </td>
                            <td class="board-number">{{ number_format($post->target_count) }}名</td>
                            <td><span class="board-share-badge board-share-{{ $post->share_type }}">{{ $post->share_type === 'individual' ? '個別共有' : '全体共有' }}</span></td>
                            <td><span class="board-target-badge">{{ $post->target_label }}</span></td>
                            <td class="board-period"><div>{{ $post->publish_from?->format('Y/m/d H:i') ?? '未設定' }}</div><div>～ {{ $post->publish_to?->format('Y/m/d H:i') ?? '期限なし' }}</div></td>
                            <td class="board-center">@if($post->is_important)<span class="board-important">重要</span>@else - @endif</td>
                            <td class="board-center">@if($post->is_pinned)<i class="fas fa-thumbtack board-pin"></i>@else - @endif</td>
                            <td class="board-number">{{ number_format($post->reads_count) }}</td>
                            <td class="board-confirmation-cell">
                                @if($post->requires_confirmation)
                                    <strong>{{ number_format($post->confirmations_count) }}/{{ number_format($post->target_count) }}</strong>
                                    <small>（{{ number_format($post->confirmation_rate, 1) }}%）</small>
                                @else
                                    <span class="board-muted">不要</span>
                                @endif
                            </td>
                            <td class="board-number">{{ number_format($post->read_rate, 1) }}%</td>
                            <td>{{ $post->updated_at?->format('Y/m/d H:i') }}</td>
                            <td class="board-sticky-right board-operation-column">
                                <div class="board-operation-wrap">
                                    <button type="button" class="board-operation-button" aria-expanded="false"><i class="fas fa-ellipsis-vertical"></i> 操作</button>
                                    <div class="board-operation-menu">
                                        <button type="button" data-board-action="preview" data-board='@json($payload)'><i class="fas fa-display"></i>表示イメージ</button>
                                        <button type="button" data-board-action="detail" data-board='@json($payload)'><i class="fas fa-file-lines"></i>詳細</button>
                                        <button type="button" data-board-action="audience-status" data-board-id="{{ $post->id }}"><i class="fas fa-users-viewfinder"></i>既読・確認状況</button>
                                        <button type="button" data-board-action="edit" data-board='@json($payload)'><i class="fas fa-pen"></i>編集</button>
                                        <form method="POST" action="{{ route('admin.operations.communication.boards.duplicate', $post) }}">
                                            @csrf
                                            <button type="submit"><i class="fas fa-copy"></i>複製</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.operations.communication.boards.destroy', $post) }}" onsubmit="return confirm('この教室からのご連絡を削除しますか？');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="board-delete-action"><i class="fas fa-trash"></i>削除</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="19" class="board-empty">教室からのご連絡データがありません。</td></tr>
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
        <div class="board-modal-header"><h2 id="boardFormTitle">新規ご連絡登録</h2><button type="button" data-board-close>×</button></div>
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
                    <label>通知日時
                        <input type="datetime-local" name="notify_at" id="boardNotifyAt">
                        <span class="board-field-help">空欄の場合は即時通知として扱います。</span>
                    </label>
                    <div class="board-form-option-card">
                        <label class="board-confirm-option-label"><input type="checkbox" name="requires_confirmation" id="boardRequiresConfirmation" value="1"><span>「確認しました」ボタンを表示</span></label>
                        <span class="board-field-help">重要な連絡で、対象者から明示的な確認を取得したい場合に使用します。</span>
                    </div>

                    <div class="board-form-full board-delivery-settings">
                        <div class="board-field-title">表示方法<span class="required">1つ以上必須</span></div>
                        <div class="board-check-row">
                            <label><input type="checkbox" name="is_listed" id="boardIsListed" value="1" checked> 掲示板に掲載</label>
                            <label><input type="checkbox" name="is_notified" id="boardIsNotified" value="1"> 通知する</label>
                        </div>
                    </div>

                    <div class="board-form-full board-share-settings">
                        <div class="board-field-title">共有区分<span class="required">必須</span></div>
                        <div class="board-target-options">
                            <label><input type="radio" name="share_type" value="all" checked> 全体共有</label>
                            <label><input type="radio" name="share_type" value="individual"> 個別共有</label>
                        </div>
                    </div>

                    <div class="board-form-full board-check-row">
                        <label><input type="checkbox" name="is_important" id="boardImportant" value="1"> 重要表示</label>
                        <label><input type="checkbox" name="is_pinned" id="boardPinned" value="1"> ピン留め</label>
                    </div>

                    <div class="board-form-full">
                        <div class="board-field-title">共有対象<span class="required">個別共有時は必須</span></div>
                        <div class="board-target-options" id="boardIndividualTargetOptions">
                            <label><input type="radio" name="target_type" value="school" checked>教室</label>
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
        <div class="board-modal-header"><h2>教室からのご連絡詳細</h2><button type="button" data-board-close>×</button></div>
        <div class="board-modal-body" id="boardDetailContent"></div>
        <div class="board-modal-footer"><button type="button" class="board-secondary-button" data-board-close>閉じる</button></div>
    </div>
</div>

<div class="board-modal" id="boardPreviewModal" aria-hidden="true">
    <div class="board-modal-backdrop" data-board-close></div>
    <div class="board-modal-dialog board-preview-dialog">
        <div class="board-modal-header"><h2>教室からのご連絡 表示イメージ</h2><button type="button" data-board-close>×</button></div>
        <div class="board-modal-body"><div id="boardPreviewContent" class="board-student-preview"></div></div>
        <div class="board-modal-footer"><button type="button" class="board-secondary-button" data-board-close>閉じる</button></div>
    </div>
</div>


<div class="board-modal" id="boardAudienceStatusModal" aria-hidden="true">
    <div class="board-modal-backdrop" data-board-close></div>
    <div class="board-modal-dialog board-modal-wide board-audience-status-dialog">
        <div class="board-modal-header"><h2>既読・確認状況</h2><button type="button" data-board-close>×</button></div>
        <div class="board-modal-body">
            <div id="boardAudienceStatusSummary" class="board-audience-status-summary"></div>
            <div class="board-audience-tabs" role="tablist" aria-label="既読・確認状況の切替">
                <button type="button" class="active" data-audience-tab="read">既読者 <span data-audience-count="read">0</span></button>
                <button type="button" data-audience-tab="unread">未読者 <span data-audience-count="unread">0</span></button>
                <button type="button" data-audience-tab="confirmed">確認者 <span data-audience-count="confirmed">0</span></button>
                <button type="button" data-audience-tab="unconfirmed">未確認者 <span data-audience-count="unconfirmed">0</span></button>
            </div>
            <div id="boardAudienceStatusContent" class="board-audience-status-content"></div>
        </div>
        <div class="board-modal-footer"><button type="button" class="board-secondary-button" data-board-close>閉じる</button></div>
    </div>
</div>

<script>
window.boardRoutes = {
    store: @json(route('admin.operations.communication.boards.store')),
    update: @json(route('admin.operations.communication.boards.update', ['boardPost' => '__ID__'])),
    audienceStatus: @json(route('admin.operations.communication.boards.audience-status', ['boardPost' => '__ID__']))
};
</script>
@endsection
