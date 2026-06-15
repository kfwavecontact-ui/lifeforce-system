@extends('layouts.admin')

@section('title', 'ロール別通知設定')

@section('content')
<div class="role-notification-page"
     data-update-url="{{ route('admin.system.role-notification-settings.update') }}"
     data-csrf-token="{{ csrf_token() }}">

    <div class="master-page-header">
        <div>
            <h1>ロール別通知設定</h1>
            <p>
                ロールごとの通知方法のデフォルト設定を管理します。
                生徒・保護者ごとの個別設定は、生徒カルテ側で上書きできます。
            </p>
        </div>

        <button type="button" class="master-primary-button" id="roleNotificationSaveButton">
            保存する
        </button>
    </div>

    <div class="master-toolbar">
        <div class="role-notification-tabs">
            @foreach ($roles as $roleCode => $roleName)
                <button type="button"
                        class="role-tab @if ($loop->first) active @endif"
                        data-role="{{ $roleCode }}">
                    {{ $roleName }}
                </button>
            @endforeach
        </div>

        <div class="master-filter-group">
            <select id="roleNotificationCategoryFilter">
                <option value="all">すべてのカテゴリ</option>
                @foreach ($notifications->pluck('category')->unique()->values() as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>

            <button type="button" class="master-secondary-button" id="roleNotificationAllOn">
                表示中をすべてON
            </button>

            <button type="button" class="master-secondary-button" id="roleNotificationAllOff">
                表示中をすべてOFF
            </button>
        </div>
    </div>

    <section class="master-table-card">
        <table class="master-table">
            <thead>
                <tr>
                    <th>カテゴリ</th>
                    <th>通知名</th>
                    <th>通知内容</th>
                    <th>ポータル</th>
                    <th>メール</th>
                    <th>LINE</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($roles as $roleCode => $roleName)
                    @foreach ($notifications as $notification)
                        @php
                            $notificationSettings = $settings->get($notification->id, collect())->keyBy('role');
                            $setting = $notificationSettings->get($roleCode);
                        @endphp

                        @if ($setting)
                            <tr class="role-notification-row"
                                data-role="{{ $roleCode }}"
                                data-category="{{ $notification->category }}"
                                @if (!$loop->parent->first) style="display:none;" @endif>

                                <td>{{ $notification->category }}</td>
                                <td>
                                    <div style="font-weight:600;">
                                        {{ $notification->name }}
                                    </div>

                                    <div style="font-size:12px;color:#94a3b8;">
                                        {{ $notification->code }}
                                    </div>
                                </td>
                                <td>
                                    <div class="notification-description">
                                        {{ $notification->description }}
                                    </div>
                                </td>

                                <td>
                                    <input type="checkbox"
                                           class="role-notification-checkbox"
                                           data-id="{{ $setting->id }}"
                                           data-channel="portal_enabled"
                                           @checked($setting->portal_enabled)>
                                </td>

                                <td>
                                    <input type="checkbox"
                                           class="role-notification-checkbox"
                                           data-id="{{ $setting->id }}"
                                           data-channel="email_enabled"
                                           @checked($setting->email_enabled)>
                                </td>

                                <td>
                                    <input type="checkbox"
                                           class="role-notification-checkbox"
                                           data-id="{{ $setting->id }}"
                                           data-channel="line_enabled"
                                           @checked($setting->line_enabled)>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.role-notification-page');
    const saveButton = document.getElementById('roleNotificationSaveButton');
    const tabs = document.querySelectorAll('.role-tab');
    const rows = document.querySelectorAll('.role-notification-row');
    const categoryFilter = document.getElementById('roleNotificationCategoryFilter');
    const allOnButton = document.getElementById('roleNotificationAllOn');
    const allOffButton = document.getElementById('roleNotificationAllOff');

    let currentRole = document.querySelector('.role-tab.active')?.dataset.role || 'admin';
    let isDirty = false;

    function refreshRows() {
        const category = categoryFilter.value;

        rows.forEach(row => {
            const roleMatched = row.dataset.role === currentRole;
            const categoryMatched = category === 'all' || row.dataset.category === category;

            row.style.display = roleMatched && categoryMatched ? '' : 'none';
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(item => item.classList.remove('active'));
            tab.classList.add('active');

            currentRole = tab.dataset.role;
            refreshRows();
        });
    });

    categoryFilter.addEventListener('change', refreshRows);

    allOnButton.addEventListener('click', () => {
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                row.querySelectorAll('.role-notification-checkbox').forEach(input => {
                    input.checked = true;
                });
            }
        });
    });

    allOffButton.addEventListener('click', () => {
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                row.querySelectorAll('.role-notification-checkbox').forEach(input => {
                    input.checked = false;
                });
            }
        });
    });

    saveButton.addEventListener('click', async () => {
        const grouped = {};

        document.querySelectorAll('.role-notification-checkbox').forEach(input => {
            const id = input.dataset.id;
            const channel = input.dataset.channel;

            if (!grouped[id]) {
                grouped[id] = {
                    id: Number(id),
                    portal_enabled: false,
                    email_enabled: false,
                    line_enabled: false,
                };
            }

            grouped[id][channel] = input.checked;
        });

        const response = await fetch(page.dataset.updateUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.dataset.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                items: Object.values(grouped),
            }),
        });

        if (!response.ok) {
            alert('保存に失敗しました。');
            return;
        }

        const result = await response.json();
        isDirty = false;
        alert(result.message || '保存しました。');
    });

    // チェック変更で未保存扱い
    document.querySelectorAll('.role-notification-checkbox').forEach(input => {
        input.addEventListener('change', () => {
            isDirty = true;
        });
    });

    // ページ離脱警告
    window.addEventListener('beforeunload', e => {
        if (!isDirty) {
            return;
        }

        e.preventDefault();
        e.returnValue = '';
    });

    refreshRows();
});
</script>
@endsection