@extends('layouts.admin')

@section('title', '通知マスタ')

@section('content')
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">通知マスタ</h1>
            <p class="admin-page-description">通知の種類を管理します。</p>
        </div>

        <a href="{{ route('admin.system.notification-masters.create') }}" class="admin-btn admin-btn-primary">
            新規作成
        </a>
    </div>

    @if (session('success'))
        <div class="admin-alert admin-alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.system.notification-masters.index') }}" class="admin-filter">
        <div class="admin-filter-row">
            <div class="admin-filter-item">
                <label>カテゴリ</label>
                <select name="category">
                    <option value="">すべて</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>
                            {{ $category }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="admin-filter-item">
                <label>キーワード</label>
                <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="通知名・コード">
            </div>

            <div class="admin-filter-actions">
                <button type="submit" class="admin-btn admin-btn-secondary">検索</button>
                <a href="{{ route('admin.system.notification-masters.index') }}" class="admin-btn admin-btn-light">リセット</a>
            </div>
        </div>
    </form>

    <div class="admin-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>表示順</th>
                    <th>通知コード</th>
                    <th>通知名</th>
                    <th>カテゴリ</th>
                    <th>初期状態</th>
                    <th>有効状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td>{{ $notification->sort_order }}</td>
                        <td>{{ $notification->code }}</td>
                        <td>{{ $notification->name }}</td>
                        <td>{{ $notification->category }}</td>
                        <td>
                            @if ($notification->default_enabled)
                                <span class="admin-badge admin-badge-success">ON</span>
                            @else
                                <span class="admin-badge admin-badge-muted">OFF</span>
                            @endif
                        </td>
                        <td>
                            @if ($notification->is_active)
                                <span class="admin-badge admin-badge-success">有効</span>
                            @else
                                <span class="admin-badge admin-badge-muted">無効</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.system.notification-masters.edit', $notification) }}" class="admin-btn admin-btn-sm admin-btn-secondary">
                                編集
                            </a>

                            <form method="POST" action="{{ route('admin.system.notification-masters.destroy', $notification) }}" class="admin-inline-form" onsubmit="return confirm('削除してよろしいですか？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">
                                    削除
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="admin-table-empty">
                            通知マスタが登録されていません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="admin-pagination">
            {{ $notifications->links() }}
        </div>
    </div>
</div>
@endsection