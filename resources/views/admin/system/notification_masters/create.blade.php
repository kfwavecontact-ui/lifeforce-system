@extends('layouts.admin')

@section('title', '通知マスタ登録')

@section('content')
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">通知マスタ登録</h1>
            <p class="admin-page-description">
                通知マスタを新規登録します。
            </p>
        </div>

        <a href="{{ route('admin.system.notification-masters.index') }}"
           class="admin-btn admin-btn-light">
            一覧へ戻る
        </a>
    </div>

    <div class="admin-card">
        <form method="POST"
              action="{{ route('admin.system.notification-masters.store') }}">

            @csrf

            @include(
                'admin.system.notification_masters._form',
                [
                    'notificationMaster' => null
                ]
            )

            <div class="admin-form-footer">
                <button type="submit"
                        class="admin-btn admin-btn-primary">
                    登録する
                </button>

                <a href="{{ route('admin.system.notification-masters.index') }}"
                   class="admin-btn admin-btn-light">
                    キャンセル
                </a>
            </div>

        </form>
    </div>
</div>
@endsection
