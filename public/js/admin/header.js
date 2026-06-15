document.addEventListener('DOMContentLoaded', () => {

    const notificationButton =
        document.getElementById('headerNotificationButton');

    const notificationPanel =
        document.getElementById('headerNotificationPanel');

    const userButton =
        document.getElementById('headerUserButton');

    const userMenu =
        document.getElementById('headerUserMenu');

    const searchInput =
        document.getElementById('headerGlobalSearchInput');

    const searchPanel =
        document.getElementById('headerGlobalSearchPanel');

    const searchResults =
        document.getElementById('headerGlobalSearchResults');

    /*
    |--------------------------------------------------------------------------
    | サンプルデータ
    |--------------------------------------------------------------------------
    */

    const searchData = [
        {
            type: '生徒',
            name: '山田 太郎',
            icon: 'fa-user-graduate',
            className: 'header-search-student'
        },
        {
            type: '生徒',
            name: '山田 次郎',
            icon: 'fa-user-graduate',
            className: 'header-search-student'
        },
        {
            type: '保護者',
            name: '山田 花子',
            icon: 'fa-people-roof',
            className: 'header-search-parent'
        },
        {
            type: '講師',
            name: '山田 一郎',
            icon: 'fa-chalkboard-user',
            className: 'header-search-teacher'
        },
        {
            type: '生徒',
            name: '鈴木 ひなた',
            icon: 'fa-user-graduate',
            className: 'header-search-student'
        },
        {
            type: '講師',
            name: '佐藤 健',
            icon: 'fa-chalkboard-user',
            className: 'header-search-teacher'
        }
    ];

    /*
    |--------------------------------------------------------------------------
    | 通知センター
    |--------------------------------------------------------------------------
    */

    if (notificationButton && notificationPanel) {

        notificationButton.addEventListener('click', (e) => {

            e.stopPropagation();

            if (userMenu) {
                userMenu.classList.remove('show');
            }

            if (searchPanel) {
                searchPanel.classList.remove('show');
            }

            notificationPanel.classList.toggle('show');
        });

        notificationPanel.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | ユーザーメニュー
    |--------------------------------------------------------------------------
    */

    if (userButton && userMenu) {

        userButton.addEventListener('click', (e) => {

            e.stopPropagation();

            if (notificationPanel) {
                notificationPanel.classList.remove('show');
            }

            if (searchPanel) {
                searchPanel.classList.remove('show');
            }

            userMenu.classList.toggle('show');
        });

        userMenu.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | グローバル検索
    |--------------------------------------------------------------------------
    */

    function renderSearchResults(keyword) {

        searchResults.innerHTML = '';

        const filtered = searchData.filter(item =>
            item.name.includes(keyword)
        );

        if (filtered.length === 0) {

            searchResults.innerHTML = `
                <div class="header-search-empty">
                    該当するデータがありません
                </div>
            `;

            return;
        }

        filtered.forEach(item => {

            const row = document.createElement('a');

            row.href = '#';
            row.className = 'header-search-result';

            row.innerHTML = `
                <div class="header-search-icon ${item.className}">
                    <i class="fas ${item.icon}"></i>
                </div>

                <div class="header-search-body">
                    <div class="header-search-name">
                        ${item.name}
                    </div>

                    <div class="header-search-type">
                        ${item.type}
                    </div>
                </div>
            `;

            searchResults.appendChild(row);
        });
    }

    if (searchInput && searchPanel && searchResults) {

        searchInput.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        searchInput.addEventListener('focus', (e) => {
            e.stopPropagation();

            if (notificationPanel) {
                notificationPanel.classList.remove('show');
            }

            if (userMenu) {
                userMenu.classList.remove('show');
            }

            searchPanel.classList.add('show');
            renderSearchResults(searchInput.value.trim());
        });

        searchInput.addEventListener('input', (e) => {
            e.stopPropagation();

            const keyword = searchInput.value.trim();

            searchPanel.classList.add('show');
            renderSearchResults(keyword);
        });

        searchPanel.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 外側クリック
    |--------------------------------------------------------------------------
    */

    document.addEventListener('click', () => {

        if (notificationPanel) {
            notificationPanel.classList.remove('show');
        }

        if (userMenu) {
            userMenu.classList.remove('show');
        }

        if (searchPanel) {
            searchPanel.classList.remove('show');
        }
    });

});