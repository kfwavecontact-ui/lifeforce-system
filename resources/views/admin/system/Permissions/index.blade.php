@extends('layouts.admin')

@section('title', '権限管理')

@section('content')
<div class="master-page permission-page">

        @if (session('success'))
            <div class="master-alert-success">
                {{ session('success') }}
            </div>
        @endif

    <div class="master-page-header">
        <div>
            <h1>権限管理</h1>
            <p>ロールごとに、各機能の利用権限を管理します。</p>
        </div>

        <button type="submit" class="master-primary-button" form="permissionForm">
            保存
        </button>
    </div>

    <section class="master-toolbar">
        <div class="master-search">
            <i class="fas fa-search"></i>
            <input type="text" id="permissionSearchInput" placeholder="権限名で検索">
        </div>
    </section>

    <form id="permissionForm" method="POST" action="{{ route('admin.system.permissions.update') }}">
    @csrf

    <section class="master-table-card">
        <table class="master-table permission-table">
            <thead>
                <tr>
                    <th>機能</th>
                    <th>権限名</th>

                    @foreach ($roles as $role)
                        <th>{{ $role->display_name }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @forelse ($permissions as $groupName => $groupPermissions)
                    <tr class="permission-group-row permission-toggle-row" data-target-group="{{ $loop->index }}">
                        <td colspan="{{ $roles->count() + 2 }}">
                            <span class="permission-arrow">▶</span>
                            {{ $groupName }}
                        </td>
                    </tr>

                    @foreach ($groupPermissions->groupBy('module') as $module => $modulePermissions)
                    @php
                        $moduleIndex = $loop->parent->index . '-' . $loop->index;
                    @endphp
                        <tr class="permission-category-row permission-toggle-row permission-child-row" data-group="{{ $loop->parent->index }}" data-target-module="{{ $moduleIndex }}" style="display:none;">
                            <td colspan="{{ $roles->count() + 2 }}">
                                <span class="permission-arrow">▶</span>
                                {{ $module }}
                            </td>
                        </tr>

                        @foreach ($modulePermissions as $permission)
                            <tr class="permission-child-row permission-module-row" data-group="{{ $loop->parent->parent->index }}" data-module="{{ $moduleIndex }}" style="display:none;">
                                <td></td>
                                <td>{{ $permission->display_name }}</td>

                                @foreach ($roles as $role)
                                    <td>
                                        <label class="permission-toggle">
                                            <input
                                                type="checkbox"
                                                name="permissions[{{ $role->id }}][]"
                                                value="{{ $permission->id }}"
                                                {{ $rolePermissionMap->has($role->id . '_' . $permission->id) ? 'checked' : '' }}
                                            >
                                            <span></span>
                                        </label>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ $roles->count() + 2 }}">
                            権限データが登録されていません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('permissionSearchInput');

    document.querySelectorAll('.permission-group-row').forEach(function (groupRow) {
        groupRow.addEventListener('click', function () {
            if (searchInput && searchInput.value.trim() !== '') {
                return;
            }

            const group = groupRow.dataset.targetGroup;

            document.querySelectorAll('.permission-category-row[data-group="' + group + '"]').forEach(function (row) {
                row.style.display = row.style.display === 'none' ? '' : 'none';
            });

            groupRow.classList.toggle('is-open');
        });
    });

    document.querySelectorAll('.permission-category-row').forEach(function (categoryRow) {
        categoryRow.addEventListener('click', function () {
            if (searchInput && searchInput.value.trim() !== '') {
                return;
            }

            const module = categoryRow.dataset.targetModule;

            document.querySelectorAll('.permission-module-row[data-module="' + module + '"]').forEach(function (row) {
                row.style.display = row.style.display === 'none' ? '' : 'none';
            });

            categoryRow.classList.toggle('is-open');
        });
    });

    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('input', function () {
        const keyword = searchInput.value.trim().toLowerCase();

        if (keyword === '') {
            document.querySelectorAll('.permission-category-row, .permission-module-row').forEach(function (row) {
                row.style.display = 'none';
            });
            document.querySelectorAll('.permission-group-row').forEach(function (row) {
                row.style.display = '';
            });

            document.querySelectorAll('.permission-toggle-row').forEach(function (row) {
                row.classList.remove('is-open');
            });


            return;
        }

        document.querySelectorAll('.permission-group-row').forEach(function (groupRow) {
            let groupVisible = false;
            const group = groupRow.dataset.targetGroup;

            document.querySelectorAll('.permission-category-row[data-group="' + group + '"]').forEach(function (categoryRow) {
                let categoryVisible = false;
                const module = categoryRow.dataset.targetModule;

                document.querySelectorAll('.permission-module-row[data-module="' + module + '"]').forEach(function (permissionRow) {
                    const matched = permissionRow.textContent.toLowerCase().includes(keyword);
                    permissionRow.style.display = matched ? '' : 'none';

                    if (matched) {
                        categoryVisible = true;
                        groupVisible = true;
                    }
                });

                categoryRow.style.display = categoryVisible ? '' : 'none';
            });

            groupRow.style.display = groupVisible ? '' : 'none';
        });
    });
});

const permissionForm = document.getElementById('permissionForm');

permissionForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(permissionForm);

    try {
        const response = await fetch(permissionForm.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        const data = await response.json();

        showPermissionToast(data.message);

    } catch (error) {
        showPermissionToast('保存に失敗しました。', true);
    }
});

function showPermissionToast(message, isError = false) {

    let toast = document.getElementById('permissionToast');

    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'permissionToast';
        document.body.appendChild(toast);
    }

    toast.textContent = message;

    toast.className = isError
        ? 'permission-toast permission-toast-error'
        : 'permission-toast permission-toast-success';

    toast.style.display = 'block';

    setTimeout(() => {
        toast.style.display = 'none';
    }, 2500);
}
</script>

@endsection