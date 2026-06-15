@extends('layouts.admin')

@section('title', '通知履歴')

@section('content')
<div class="master-page">

    <div class="master-page-header">
        <div>
            <h1>通知履歴</h1>
            <p>実際に送信・表示された通知の履歴を確認します。</p>
        </div>
    </div>

    <form method="GET"
        action="{{ route('admin.system.notification-histories') }}"
        class="history-filter-card">

        <div class="history-filter-row history-filter-row-top">
            <div class="history-search">
                <i class="fas fa-search"></i>
                <input type="text"
                    name="keyword"
                    value="{{ request('keyword') }}"
                    placeholder="送信者・受信者・タイトルで検索">
            </div>

            <div class="history-date-group">
                <span class="history-date-label">送信期間</span>

                <input type="date"
                    name="date_from"
                    value="{{ request('date_from') }}"
                    class="history-input history-date-input">

                <span class="history-date-separator">～</span>

                <input type="date"
                    name="date_to"
                    value="{{ request('date_to') }}"
                    class="history-input history-date-input">
            </div>
        </div>

        <div class="history-filter-row history-filter-row-bottom">
            <select name="category" class="history-input">
                <option value="">すべてのカテゴリ</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>
                        {{ $category }}
                    </option>
                @endforeach
            </select>

            <select name="notification_master_id" class="history-input">
                <option value="">すべての通知</option>
                @foreach ($notificationMasters as $master)
                    <option value="{{ $master->id }}" @selected((string) request('notification_master_id') === (string) $master->id)>
                        {{ $master->name }}
                    </option>
                @endforeach
            </select>

            <select name="recipient_role" class="history-input">
                <option value="">すべての受信者</option>
                <option value="student" @selected(request('recipient_role') === 'student')>生徒</option>
                <option value="parent" @selected(request('recipient_role') === 'parent')>保護者</option>
                <option value="teacher" @selected(request('recipient_role') === 'teacher')>講師</option>
                <option value="school_manager" @selected(request('recipient_role') === 'school_manager')>教室長</option>
                <option value="area_manager" @selected(request('recipient_role') === 'area_manager')>エリアマネージャ</option>
                <option value="head_office" @selected(request('recipient_role') === 'head_office')>本部</option>
                <option value="admin" @selected(request('recipient_role') === 'admin')>管理者</option>
            </select>

            <select name="channel" class="history-input">
                <option value="">すべてのチャネル</option>
                <option value="portal" @selected(request('channel') === 'portal')>ポータル</option>
                <option value="email" @selected(request('channel') === 'email')>メール</option>
                <option value="line" @selected(request('channel') === 'line')>LINE</option>
            </select>

            <select name="status" class="history-input">
                <option value="">すべての状態</option>
                <option value="sent" @selected(request('status') === 'sent')>送信済</option>
                <option value="read" @selected(request('status') === 'read')>既読</option>
                <option value="failed" @selected(request('status') === 'failed')>失敗</option>
            </select>

            <button type="submit" class="history-filter-button">
                検索
            </button>

            <a href="{{ route('admin.system.notification-histories') }}"
            class="history-reset-button">
                リセット
            </a>
        </div>
    </form>




    <section class="master-table-card">
        <table class="master-table notification-history-table">
            <thead>
                <tr>
                    <th>送信日時</th>
                    <th>カテゴリ</th>
                    <th>通知名</th>
                    <th>送信者</th>
                    <th>受信者</th>
                    <th>チャネル</th>
                    <th>状態</th>
                    <th>既読日時</th>
                    <th>操作</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($histories as $history)
                    <tr>
                        <td>{{ $history->sent_at ? $history->sent_at->format('Y/m/d H:i') : '-' }}</td>

                        <td>{{ $history->notificationMaster?->category ?? '-' }}</td>

                        <td>{{ $history->notificationMaster?->name ?? '-' }}</td>

                        <td>
                            {{ $history->sender_name ?? 'システム' }}
                            @if ($history->sender_role)
                                <div class="notification-person-sub">
                                    {{ [
                                        'system' => 'システム',
                                        'admin' => '管理者',
                                        'head_office' => '本部',
                                        'area_manager' => 'エリアマネージャ',
                                        'school_manager' => '教室長',
                                        'teacher' => '講師',
                                    ][$history->sender_role] ?? $history->sender_role }}
                                </div>
                            @endif
                        </td>

                        <td>
                            {{ $history->recipient_name ?? '-' }}
                            @if ($history->recipient_role)
                                <div class="notification-person-sub">
                                    {{ [
                                        'admin' => '管理者',
                                        'head_office' => '本部',
                                        'area_manager' => 'エリアマネージャ',
                                        'school_manager' => '教室長',
                                        'teacher' => '講師',
                                        'student' => '生徒',
                                        'parent' => '保護者',
                                    ][$history->recipient_role] ?? $history->recipient_role }}
                                </div>
                            @endif
                        </td>

                        <td>
                            {{ [
                                'portal' => 'ポータル',
                                'email' => 'メール',
                                'line' => 'LINE',
                            ][$history->channel] ?? $history->channel }}
                        </td>

                        <td>
                            @if ($history->status === 'sent')
                                <span class="master-status active">送信済</span>
                            @elseif ($history->status === 'read')
                                <span class="master-status active">既読</span>
                            @elseif ($history->status === 'failed')
                                <span class="master-status inactive">失敗</span>
                            @else
                                <span class="master-status">{{ $history->status }}</span>
                            @endif
                        </td>

                        <td>{{ $history->read_at ? $history->read_at->format('Y/m/d H:i') : '-' }}</td>

                        <td>
                            <button type="button"
                                    class="master-secondary-button notification-history-detail-button"
                                    data-title="{{ e($history->title) }}"
                                    data-body="{{ e($history->body ?? '') }}"
                                    data-sender-id="{{ $history->sender_user_id ?? '' }}"
                                    data-recipient-id="{{ $history->recipient_user_id ?? '' }}"
                                    data-sender-role="{{ e($history->sender_role ?? '') }}"
                                    data-recipient-role="{{ e($history->recipient_role ?? '') }}"
                                    data-error="{{ e($history->error_message ?? '') }}"
                                    data-sender="{{ e(($history->sender_name ?? 'システム') . ($history->sender_role ? '（' . ([
                                        'system' => 'システム',
                                        'admin' => '管理者',
                                        'head_office' => '本部',
                                        'area_manager' => 'エリアマネージャ',
                                        'school_manager' => '教室長',
                                        'teacher' => '講師',
                                    ][$history->sender_role] ?? $history->sender_role) . '）' : '')) }}"
                                    data-recipient="{{ e(($history->recipient_name ?? '-') . ($history->recipient_role ? '（' . ([
                                        'admin' => '管理者',
                                        'head_office' => '本部',
                                        'area_manager' => 'エリアマネージャ',
                                        'school_manager' => '教室長',
                                        'teacher' => '講師',
                                        'student' => '生徒',
                                        'parent' => '保護者',
                                    ][$history->recipient_role] ?? $history->recipient_role) . '）' : '')) }}">
                                詳細
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;color:#64748b;padding:32px;">
                            通知履歴はまだありません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div style="margin-top:16px;">
        {{ $histories->links() }}
    </div>

</div>


<div class="master-modal" id="notificationHistoryDetailModal">
    <div class="master-modal-content notification-history-modal">
        <div class="master-modal-header">
            <h3>通知履歴詳細</h3>

            <button type="button"
                    class="master-modal-close"
                    id="notificationHistoryDetailClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="master-form">
            <label>
                タイトル
                <input type="text" id="notificationHistoryDetailTitle" readonly>
            </label>

            <label>
                送信者ID
                <input type="text" id="notificationHistoryDetailSenderId" readonly>
            </label>



            <label>
                送信者
                <input type="text" id="notificationHistoryDetailSender" readonly>
            </label>

            <label>
                送信者ロール
                <input type="text" id="notificationHistoryDetailSenderRole" readonly>
            </label>

            <label>
                受信者ID
                <input type="text" id="notificationHistoryDetailRecipientId" readonly>
            </label>

            <label>
                受信者
                <input type="text" id="notificationHistoryDetailRecipient" readonly>
            </label>

            <label>
                受信者ロール
                <input type="text" id="notificationHistoryDetailRecipientRole" readonly>
            </label>

            <label>
                本文
                <textarea id="notificationHistoryDetailBody" rows="6" readonly></textarea>
            </label>

            <label>
                エラー内容
                <textarea id="notificationHistoryDetailError" rows="4" readonly></textarea>
            </label>
        </div>

        <div class="master-modal-footer">
            <button type="button"
                    class="master-secondary-button"
                    id="notificationHistoryDetailCancel">
                閉じる
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const modal = document.getElementById('notificationHistoryDetailModal');

    const closeButton = document.getElementById('notificationHistoryDetailClose');
    const cancelButton = document.getElementById('notificationHistoryDetailCancel');

    const titleInput = document.getElementById('notificationHistoryDetailTitle');
    const senderInput = document.getElementById('notificationHistoryDetailSender');
    const recipientInput = document.getElementById('notificationHistoryDetailRecipient');
    const senderIdInput = document.getElementById('notificationHistoryDetailSenderId');
    const recipientIdInput = document.getElementById('notificationHistoryDetailRecipientId');
    const senderRoleInput = document.getElementById('notificationHistoryDetailSenderRole');
    const recipientRoleInput = document.getElementById('notificationHistoryDetailRecipientRole');
    const bodyInput = document.getElementById('notificationHistoryDetailBody');
    const errorInput = document.getElementById('notificationHistoryDetailError');

    document
        .querySelectorAll('.notification-history-detail-button')
        .forEach(button => {

            button.addEventListener('click', () => {

                titleInput.value = button.dataset.title || '';
                senderIdInput.value = button.dataset.senderId || '';
                senderInput.value = button.dataset.sender || '';
                senderRoleInput.value = button.dataset.senderRole || '';
                recipientIdInput.value = button.dataset.recipientId || '';
                recipientInput.value = button.dataset.recipient || '';
                recipientRoleInput.value = button.dataset.recipientRole || '';
                bodyInput.value = button.dataset.body || '';
                errorInput.value = button.dataset.error || '';


                modal.classList.add('show');
            });
        });

    function closeModal()
    {
        modal.classList.remove('show');
    }

    closeButton?.addEventListener('click', closeModal);
    cancelButton?.addEventListener('click', closeModal);

    modal?.addEventListener('click', e => {

        if (e.target === modal) {
            closeModal();
        }
    });
});
</script>


@endsection