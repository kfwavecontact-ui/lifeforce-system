<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? '生きる力アカデミー管理基盤' }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">

    
</head>
<body>

<div class="layout">

    <aside class="sidebar">

        <div class="logo">
            生きる力アカデミー
        </div>

        <div class="menu-title">ダッシュボード</div>
        <div class="menu-item">ホーム</div>

        <div class="menu-title">会員管理</div>
        <div class="menu-item">生徒一覧</div>
        <div class="menu-item">保護者一覧</div>
        <div class="menu-item">講師一覧</div>

        <div class="menu-title">授業管理</div>
        <div class="menu-item">授業一覧</div>
        <div class="menu-item">出席管理</div>

        <div class="menu-title">学習管理</div>
        <div class="menu-item">学習計画</div>
        <div class="menu-item">ルーティン管理</div>
        <div class="menu-item">バッジ管理</div>

        <div class="menu-title">請求管理</div>
        <div class="menu-item">請求一覧</div>
        <div class="menu-item">入金管理</div>

        <div class="menu-title">システム管理</div>
        <div class="menu-item">権限管理</div>
        <div class="menu-item">マスタ管理</div>

    </aside>

    <main class="main">

        <header class="header">
            <div class="header-breadcrumb">
                @yield('breadcrumb')
            </div>

            <div class="header-user-area">

                <div class="header-notification">
                    🔔
                    <span>12</span>
                </div>

                <div class="header-user-avatar">
                    田
                </div>

                <div class="header-user-info">
                    <div class="header-user-name">田中 太郎</div>
                    <div class="header-user-role">教室長</div>
                </div>

                <div class="header-user-arrow">
                    ▾
                </div>

            </div>
        </header>

        <div class="page">

            @yield('content')

        </div>

    </main>

</div>

</body>
</html>