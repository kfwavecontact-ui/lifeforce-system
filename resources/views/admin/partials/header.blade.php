<header class="admin-header">
    <div class="admin-header-left">
        <nav class="admin-breadcrumb">
            <a href="#">ホーム</a>
            <span>＞</span>
            <a href="#">会員管理</a>
            <span>＞</span>
            <a href="#">生徒一覧</a>
            <span>＞</span>
            <strong>生徒カルテ</strong>
        </nav>

        <div class="header-global-search">
            <i class="fas fa-search"></i>
            <input
                type="text"
                id="headerGlobalSearchInput"
                placeholder="生徒・講師・保護者を検索..."
                autocomplete="off"
            >

            <div class="header-global-search-panel" id="headerGlobalSearchPanel">
                <div class="header-global-search-title">検索結果</div>
                <div id="headerGlobalSearchResults"></div>
            </div>
        </div>
    </div>

    <div class="admin-header-right">

        <div class="header-notification">
            <button type="button" class="header-icon-button" id="headerNotificationButton">
                <i class="fas fa-bell"></i>
                <span class="header-notification-badge">12</span>
            </button>

            <div class="notification-panel" id="headerNotificationPanel">
                <div class="notification-panel-header">
                    <div>
                        <div class="notification-panel-title">通知センター</div>
                        <div class="notification-panel-subtitle">未確認の通知が12件あります</div>
                    </div>
                    <button type="button" class="notification-panel-action">すべて確認</button>
                </div>

                <div class="notification-list">
                    <a href="#" class="notification-item">
                        <div class="notification-icon notification-icon-red"><i class="fas fa-yen-sign"></i></div>
                        <div class="notification-body">
                            <div class="notification-title">未収金あり 1件</div>
                            <div class="notification-text">入金確認が必要な請求があります。</div>
                            <div class="notification-meta">会計</div>
                        </div>
                        <div class="notification-count red">1</div>
                    </a>

                    <a href="#" class="notification-item">
                        <div class="notification-icon notification-icon-orange"><i class="fas fa-rotate-right"></i></div>
                        <div class="notification-body">
                            <div class="notification-title">振替未予約 1件</div>
                            <div class="notification-text">振替予約が未完了の授業があります。</div>
                            <div class="notification-meta">スケジュール</div>
                        </div>
                        <div class="notification-count orange">1</div>
                    </a>

                    <a href="#" class="notification-item">
                        <div class="notification-icon notification-icon-blue"><i class="fas fa-book-open"></i></div>
                        <div class="notification-body">
                            <div class="notification-title">学習計画期限超過 1件</div>
                            <div class="notification-text">期限を過ぎた計画学習があります。</div>
                            <div class="notification-meta">計画学習</div>
                        </div>
                        <div class="notification-count blue">1</div>
                    </a>
                </div>

                <div class="notification-panel-footer">
                    <a href="#">すべての通知を見る</a>
                </div>
            </div>
        </div>

        <button type="button" class="header-icon-button">
            <i class="fas fa-th-large"></i>
        </button>

        <div class="header-user-wrap">
            <button type="button" class="header-user" id="headerUserButton">
                <div class="header-user-avatar">田</div>
                <div class="header-user-info">
                    <div class="header-user-name">田中 太郎</div>
                    <div class="header-user-role">教室長</div>
                </div>
                <i class="fas fa-chevron-down header-user-arrow"></i>
            </button>

            <div class="header-user-menu" id="headerUserMenu">
                <a href="#" class="header-user-menu-item"><i class="fas fa-user"></i><span>プロフィール</span></a>
                <a href="#" class="header-user-menu-item"><i class="fas fa-cog"></i><span>アカウント設定</span></a>
                <a href="#" class="header-user-menu-item"><i class="fas fa-key"></i><span>パスワード変更</span></a>
                <div class="header-user-menu-divider"></div>
                <a href="#" class="header-user-menu-item logout"><i class="fas fa-sign-out-alt"></i><span>ログアウト</span></a>
            </div>
        </div>

    </div>
</header>