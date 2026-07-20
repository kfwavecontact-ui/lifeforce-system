@extends('layouts.admin')
@section('title', '一斉通知')
@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/bulk-notifications.css') }}">
<div class="bulk-page">
    <div class="bulk-header">
        <div>
            <h1>一斉通知</h1>
            <p>生徒・保護者への通知を作成し、下書きまたは予約として管理します。</p>
        </div>
        <button class="bulk-primary bulk-new-button" type="button" data-bulk-open @disabled($notificationMasters->isEmpty()) title="{{ $notificationMasters->isEmpty() ? '通知マスタを登録してください' : '' }}">＋ 新規一斉通知</button>
    </div>

    @if(session('success'))<div class="bulk-alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bulk-alert error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if($notificationMasters->isEmpty())<div class="bulk-alert error">通知マスタが未登録のため、一斉通知を作成できません。<a href="{{ route('admin.system.notification-masters') }}">通知マスタを開く</a></div>@endif

    <section class="bulk-search-card">
        <form method="GET" id="bulkListFilterForm">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="bulk-search-grid">
                <label>キーワード<input name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="タイトル・本文"></label>
                <label>通知種類<select name="notification_master_id"><option value="">すべて</option>@foreach($notificationMasters as $master)<option value="{{ $master->id }}" @selected(($filters['notification_master_id'] ?? '') == $master->id)>{{ $master->name }}</option>@endforeach</select></label>
                <label>送信対象<select name="target_type"><option value="">すべて</option>@foreach(['all'=>'全体','school'=>'教室','grade'=>'学年','course'=>'コース','student'=>'生徒個別'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['target_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>状態<select name="notification_status"><option value="">すべて</option>@foreach(['draft'=>'下書き','scheduled'=>'予約','sent'=>'送信済み','cancelled'=>'取消'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['notification_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>送信予定日 From<input type="date" name="scheduled_from" value="{{ $filters['scheduled_from'] ?? '' }}"></label>
                <label>送信予定日 To<input type="date" name="scheduled_to" value="{{ $filters['scheduled_to'] ?? '' }}"></label>
            </div>
            <div class="bulk-search-footer">
                <label class="bulk-inline-check"><input type="checkbox" name="important" value="1" @checked(($filters['important'] ?? '') == 1)> 重要のみ</label>
                <div class="bulk-search-actions"><button class="bulk-primary" type="submit">検索</button><a class="bulk-secondary" href="{{ route('admin.operations.communication.bulk-notifications.index') }}">クリア</a></div>
            </div>
        </form>
    </section>

    <section class="bulk-table-card">
        <div class="bulk-count">全{{ number_format($notifications->total()) }}件</div>
        <div class="bulk-table-scroll">
            <table class="bulk-table">
                <colgroup>
                    <col class="bulk-col-id">
                    <col class="bulk-col-title">
                    <col><col><col><col><col><col><col><col><col>
                    <col class="bulk-col-operation">
                </colgroup>
                <thead>
                    @php
                        $sortUrl=function(string $column)use($sort,$direction){$next=($sort===$column&&$direction==='asc')?'desc':'asc';return request()->fullUrlWithQuery(['sort'=>$column,'direction'=>$next,'page'=>null]);};
                        $sortIcon=fn(string $column)=>$sort===$column?($direction==='asc'?'fa-sort-up':'fa-sort-down'):'fa-sort';
                    @endphp
                    <tr>
                        <th class="bulk-sticky-left bulk-id-column">
                            <div class="bulk-column-header"><a class="bulk-sort-link" href="{{ $sortUrl('id') }}">ID <i class="fas {{ $sortIcon('id') }}"></i></a><button type="button" class="bulk-filter-trigger @if(($filters['id_min'] ?? '') !== '' || ($filters['id_max'] ?? '') !== '') active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">ID</div><label>最小<input form="bulkListFilterForm" type="number" name="id_min" value="{{ $filters['id_min'] ?? '' }}"></label><label>最大<input form="bulkListFilterForm" type="number" name="id_max" value="{{ $filters['id_max'] ?? '' }}"></label><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th class="bulk-sticky-left bulk-title-column">
                            <div class="bulk-column-header bulk-title-header"><a class="bulk-sort-link" href="{{ $sortUrl('title') }}">タイトル <i class="fas {{ $sortIcon('title') }}"></i></a><button type="button" class="bulk-filter-trigger @if(($filters['title_filter'] ?? '') !== '') active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">タイトル</div><label>文字を含む<input form="bulkListFilterForm" type="text" name="title_filter" value="{{ $filters['title_filter'] ?? '' }}"></label><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th>
                            <div class="bulk-column-header"><span>通知種類</span><button type="button" class="bulk-filter-trigger @if(!empty($filters['master_filter'])) active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">通知種類</div><div class="bulk-filter-option-list">@foreach($notificationMasters as $master)<label><input form="bulkListFilterForm" type="checkbox" name="master_filter[]" value="{{ $master->id }}" @checked(in_array((string)$master->id,array_map('strval',(array)($filters['master_filter'] ?? [])),true))>{{ $master->name }}</label>@endforeach</div><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th>
                            <div class="bulk-column-header"><span>送信対象</span><button type="button" class="bulk-filter-trigger @if(!empty($filters['target_filter'])) active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">送信対象</div><div class="bulk-filter-option-list">@foreach(['all'=>'全体','school'=>'教室','grade'=>'学年','course'=>'コース','student'=>'生徒個別'] as $value=>$label)<label><input form="bulkListFilterForm" type="checkbox" name="target_filter[]" value="{{ $value }}" @checked(in_array($value,(array)($filters['target_filter'] ?? []),true))>{{ $label }}</label>@endforeach</div><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th>
                            <div class="bulk-column-header"><span>受信者</span><button type="button" class="bulk-filter-trigger @if(!empty($filters['recipient_filter'])) active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">受信者</div><div class="bulk-filter-option-list">@foreach(['student'=>'生徒','parent'=>'保護者'] as $value=>$label)<label><input form="bulkListFilterForm" type="checkbox" name="recipient_filter[]" value="{{ $value }}" @checked(in_array($value,(array)($filters['recipient_filter'] ?? []),true))>{{ $label }}</label>@endforeach</div><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th>
                            <div class="bulk-column-header"><a class="bulk-sort-link" href="{{ $sortUrl('scheduled_at') }}">送信予定 <i class="fas {{ $sortIcon('scheduled_at') }}"></i></a><button type="button" class="bulk-filter-trigger @if(($filters['scheduled_from_filter'] ?? '') !== '' || ($filters['scheduled_to_filter'] ?? '') !== '') active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">送信予定</div><label>From<input form="bulkListFilterForm" type="date" name="scheduled_from_filter" value="{{ $filters['scheduled_from_filter'] ?? '' }}"></label><label>To<input form="bulkListFilterForm" type="date" name="scheduled_to_filter" value="{{ $filters['scheduled_to_filter'] ?? '' }}"></label><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th><div class="bulk-column-header"><a class="bulk-sort-link" href="{{ $sortUrl('sent_at') }}">送信日時 <i class="fas {{ $sortIcon('sent_at') }}"></i></a></div></th>
                        <th>既読</th><th>重要</th>
                        <th>
                            <div class="bulk-column-header"><a class="bulk-sort-link" href="{{ $sortUrl('notification_status') }}">状態 <i class="fas {{ $sortIcon('notification_status') }}"></i></a><button type="button" class="bulk-filter-trigger @if(!empty($filters['status_filter'])) active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">状態</div><div class="bulk-filter-option-list">@foreach(['draft'=>'下書き','scheduled'=>'予約','sent'=>'送信済み','cancelled'=>'取消'] as $value=>$label)<label><input form="bulkListFilterForm" type="checkbox" name="status_filter[]" value="{{ $value }}" @checked(in_array($value,(array)($filters['status_filter'] ?? []),true))>{{ $label }}</label>@endforeach</div><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th>
                            <div class="bulk-column-header"><a class="bulk-sort-link" href="{{ $sortUrl('updated_at') }}">更新日時 <i class="fas {{ $sortIcon('updated_at') }}"></i></a><button type="button" class="bulk-filter-trigger @if(($filters['updated_from'] ?? '') !== '' || ($filters['updated_to'] ?? '') !== '') active @endif" data-bulk-filter-trigger><i class="fas fa-filter"></i></button></div>
                            <div class="bulk-column-filter-menu" data-bulk-filter-menu><div class="bulk-filter-menu-title">更新日時</div><label>From<input form="bulkListFilterForm" type="date" name="updated_from" value="{{ $filters['updated_from'] ?? '' }}"></label><label>To<input form="bulkListFilterForm" type="date" name="updated_to" value="{{ $filters['updated_to'] ?? '' }}"></label><div class="bulk-filter-menu-actions"><button form="bulkListFilterForm" type="submit">適用</button><button type="button" data-bulk-filter-clear>解除</button></div></div>
                        </th>
                        <th class="bulk-sticky-right bulk-operation-column">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                    @php $payload=['id'=>$notification->id,'master_id'=>$notification->notification_master_id,'master_name'=>$notification->notificationMaster?->name ?? '-','title'=>$notification->title,'body'=>$notification->body,'status'=>$notification->notification_status,'status_label'=>['draft'=>'下書き','scheduled'=>'予約','sent'=>'送信済み','cancelled'=>'取消'][$notification->notification_status] ?? $notification->notification_status,'scheduled_at'=>optional($notification->scheduled_at)->format('Y-m-d\TH:i'),'scheduled_label'=>optional($notification->scheduled_at)->format('Y/m/d H:i') ?? '-','sent_label'=>optional($notification->sent_at)->format('Y/m/d H:i') ?? '-','expires_at'=>optional($notification->expires_at)->format('Y-m-d\TH:i'),'expires_label'=>optional($notification->expires_at)->format('Y/m/d H:i') ?? '-','important'=>$notification->is_important,'modal'=>$notification->show_login_modal,'confirmation'=>$notification->confirmation_required,'board_post_id'=>$notification->board_post_id,'board'=>($notification->boardPost ? ['id'=>$notification->boardPost->id,'title'=>$notification->boardPost->title,'category'=>$notification->boardPost->category,'body'=>$notification->boardPost->body,'is_important'=>$notification->boardPost->is_important,'is_pinned'=>$notification->boardPost->is_pinned,'publish_from'=>optional($notification->boardPost->publish_from)->format('Y/m/d H:i'),'publish_to'=>optional($notification->boardPost->publish_to)->format('Y/m/d H:i')] : null),'link_url'=>$notification->link_url,'target_type'=>$notification->targets->first()?->target_type,'target_label'=>$notification->target_label,'target_ids'=>$notification->targets->pluck('target_id')->filter()->unique()->values(),'recipient_types'=>$notification->targets->pluck('recipient_type')->unique()->values(),'recipient_label'=>$notification->recipient_label,'read_count'=>$notification->read_count,'confirmed_count'=>$notification->confirmed_count,'recipients_count'=>$notification->recipients_count,'created_label'=>optional($notification->created_at)->format('Y/m/d H:i') ?? '-','updated_label'=>optional($notification->updated_at)->format('Y/m/d H:i') ?? '-','attachments'=>$notification->attachment_items]; @endphp
                    <tr>
                        <td class="bulk-sticky-left bulk-id-column">{{ $notification->id }}</td>
                        <td class="bulk-sticky-left bulk-title-column"><span class="bulk-title-text">{{ $notification->title }}</span></td>
                        <td>{{ $notification->notificationMaster?->name ?? '-' }}</td><td>{{ $notification->target_label }}</td><td>{{ $notification->recipient_label }}</td>
                        <td>{{ optional($notification->scheduled_at)->format('Y/m/d H:i') ?? '-' }}</td><td>{{ optional($notification->sent_at)->format('Y/m/d H:i') ?? '-' }}</td>
                        <td class="bulk-number">{{ $notification->read_count }}/{{ $notification->recipients_count }}</td><td class="bulk-center">@if($notification->is_important)<span class="badge important">重要</span>@else - @endif</td>
                        <td><span class="badge status-{{ $notification->notification_status }}">{{ ['draft'=>'下書き','scheduled'=>'予約','sent'=>'送信済み','cancelled'=>'取消'][$notification->notification_status] ?? $notification->notification_status }}</span></td>
                        <td>{{ $notification->updated_at->format('Y/m/d H:i') }}</td>
                        <td class="bulk-sticky-right bulk-operation-column"><div class="bulk-operation-wrap"><button class="bulk-menu-button" type="button" data-bulk-menu><i class="fas fa-ellipsis-v"></i> 操作</button><div class="bulk-menu"><button type="button" data-bulk-preview='@json($payload)'><i class="fas fa-desktop"></i>表示イメージ</button><button type="button" data-bulk-detail='@json($payload)'><i class="fas fa-file-alt"></i>詳細</button>@if($notification->notification_status!=='sent')<button type="button" data-bulk-edit='@json($payload)'><i class="fas fa-pencil-alt"></i>編集</button>@endif<form method="POST" action="{{ route('admin.operations.communication.bulk-notifications.duplicate',$notification) }}">@csrf<button><i class="fas fa-copy"></i>複製</button></form>@if($notification->notification_status!=='sent')<form method="POST" action="{{ route('admin.operations.communication.bulk-notifications.destroy',$notification) }}" onsubmit="return confirm('削除しますか？')">@csrf @method('DELETE')<button class="danger"><i class="fas fa-trash"></i>削除</button></form>@endif</div></div></td>
                    </tr>
                    @empty<tr><td colspan="12" class="bulk-empty">一斉通知はありません。</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="bulk-pagination">{{ $notifications->links() }}</div>
    </section>
</div>

<div class="bulk-modal" id="bulkFormModal"><div class="bulk-backdrop" data-bulk-close></div><div class="bulk-dialog wide"><div class="bulk-modal-header"><h2 id="bulkFormTitle">新規一斉通知</h2><button data-bulk-close>×</button></div><form id="bulkForm" method="POST" enctype="multipart/form-data" action="{{ route('admin.operations.communication.bulk-notifications.store') }}">@csrf<input type="hidden" name="_method" id="bulkMethod" value="POST"><div class="bulk-modal-body"><div class="bulk-grid">
<label>通知種類<span>必須</span>
@if($notificationMasters->isEmpty())
<select id="bulkMaster" disabled><option>通知種類が登録されていません</option></select>
<div class="bulk-master-empty">通知マスタに有効な通知種類がありません。<a href="{{ route('admin.system.notification-masters') }}">通知マスタを開く</a></div>
@else
<select name="notification_master_id" id="bulkMaster" required>@foreach($notificationMasters as $master)<option value="{{ $master->id }}">{{ $master->name }}</option>@endforeach</select>
@endif
</label>
<label>状態<span>必須</span><select name="notification_status" id="bulkStatus"><option value="draft">下書き</option><option value="scheduled">予約</option></select></label>
<label class="full">タイトル<span>必須</span><input name="title" id="bulkTitle" maxlength="255" required></label>
<label class="full">本文<span>必須</span><textarea name="body" id="bulkBody" rows="9" required></textarea></label>
<label class="full">添付ファイル（1件20MB・合計10件まで）<input type="file" id="bulkAttachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"></label><div class="full" id="bulkSelectedAttachments"></div><div class="full" id="bulkCurrentAttachments"></div>
<label>送信予定日時<input type="datetime-local" name="scheduled_at" id="bulkScheduledAt"></label><label>表示終了日時<input type="datetime-local" name="expires_at" id="bulkExpiresAt"></label>
<div class="full bulk-checks"><label><input type="checkbox" name="is_important" id="bulkImportant" value="1">重要通知</label><input type="hidden" name="show_login_modal" value="0"><input type="hidden" name="confirmation_required" value="0"></div>
<div class="full"><div class="bulk-label">送信対象<span>必須</span></div><div class="bulk-options">@foreach(['all'=>'全体','school'=>'教室','grade'=>'学年','course'=>'コース','student'=>'生徒個別'] as $v=>$l)<label><input type="radio" name="target_type" value="{{ $v }}" @checked($v==='all')>{{ $l }}</label>@endforeach</div><select multiple name="target_ids[]" data-target="school">@foreach($schools as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><select multiple name="target_ids[]" data-target="grade">@foreach($grades as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><select multiple name="target_ids[]" data-target="course">@foreach($courses as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select><select multiple name="target_ids[]" data-target="student">@foreach($students as $x)<option value="{{ $x->id }}">{{ $x->student_code }} {{ $x->last_name }} {{ $x->first_name }}</option>@endforeach</select></div>
<div class="full"><div class="bulk-label">受信者<span>必須</span></div><div class="bulk-options"><label><input type="checkbox" name="recipient_types[]" value="student" checked>生徒</label><label><input type="checkbox" name="recipient_types[]" value="parent" checked>保護者</label></div></div>
<label>リンク先掲示板<select name="board_post_id" id="bulkBoard"><option value="">なし</option>@foreach($boards as $board)<option value="{{ $board->id }}">#{{ $board->id }} {{ $board->title }}</option>@endforeach</select></label><label>任意URL<input type="url" name="link_url" id="bulkLinkUrl"></label>
</div></div><div class="bulk-modal-footer"><button type="button" class="bulk-secondary" data-bulk-close>キャンセル</button><button class="bulk-primary">保存</button></div></form></div></div>
<div class="bulk-modal" id="bulkInfoModal"><div class="bulk-backdrop" data-bulk-close></div><div class="bulk-dialog"><div class="bulk-modal-header"><h2 id="bulkInfoTitle">一斉通知詳細</h2><button data-bulk-close>×</button></div><div class="bulk-modal-body" id="bulkInfoContent"></div><div class="bulk-modal-footer"><button class="bulk-secondary" data-bulk-close>閉じる</button></div></div></div>
<script>window.bulkRoutes={store:@json(route('admin.operations.communication.bulk-notifications.store')),update:@json(route('admin.operations.communication.bulk-notifications.update','__ID__'))};</script>
<script src="{{ asset('js/admin/bulk-notifications.js') }}"></script>
@endsection
