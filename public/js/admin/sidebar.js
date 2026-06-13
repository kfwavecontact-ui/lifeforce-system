document.addEventListener('DOMContentLoaded', () => {
    const RECENT_KEY = 'lifeforce_sidebar_recent_items';
    const FAVORITE_KEY = 'lifeforce_sidebar_favorite_items';
    const RECENT_LIMIT = 8;
    const FAVORITE_LIMIT = 8;

    const toggles = document.querySelectorAll('.sidebar-toggle');
    const searchInput = document.getElementById('sidebarSearchInput');
    const searchPanel = document.getElementById('sidebarSearchPanel');
    const searchResults = document.getElementById('sidebarSearchResults');
    const recentArea = document.getElementById('sidebarRecentArea');
    const sidebarSearch = document.querySelector('.sidebar-search');

    function normalizeText(text) {
        return text.replace(/\s+/g, ' ').trim();
    }

    const searchableItems = [];

    document.querySelectorAll('[data-search-name]').forEach(item => {
        searchableItems.push({
            name: normalizeText(item.dataset.searchName),
            info: item.dataset.info || '',
            target: item
        });
    });

    function getStorage(key) {
        try {
            return JSON.parse(localStorage.getItem(key)) || [];
        } catch {
            return [];
        }
    }

    function setStorage(key, value) {
        localStorage.setItem(key, JSON.stringify(value));
    }

    function buildSearchNameHtml(fullName) {
        const parts = fullName.split('＞').map(part => part.trim());

        if (parts.length <= 1) {
            return `<span class="sidebar-search-strong">${fullName}</span>`;
        }

        const last = parts.pop();
        const prefix = parts.join(' ＞ ');

        return `
            <span class="sidebar-search-muted">${prefix} ＞ </span>
            <span class="sidebar-search-strong">${last}</span>
        `;
    }

    function openSubmenuByTarget(target) {
        const submenu = target.closest('.sidebar-submenu');
        if (!submenu) return;

        document.querySelectorAll('.sidebar-toggle').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.sidebar-submenu').forEach(s => s.classList.remove('show'));

        submenu.classList.add('show');

        const parentToggle = submenu.previousElementSibling;
        if (parentToggle && parentToggle.classList.contains('sidebar-toggle')) {
            parentToggle.classList.add('active');
        }
    }

    function saveRecentItem(item) {
        const current = getStorage(RECENT_KEY);

        const next = [
            { name: item.name, info: item.info || '' },
            ...current.filter(row => row.name !== item.name)
        ].slice(0, RECENT_LIMIT);

        setStorage(RECENT_KEY, next);
        renderRecentItems();
    }

    function moveToItem(item) {
        openSubmenuByTarget(item.target);
        saveRecentItem(item);

        const href = item.target.getAttribute('href');
        if (href && href !== '#') {
            window.location.href = href;
        }
    }

    function createResultRow(item) {
        const row = document.createElement('a');
        row.href = '#';
        row.className = 'sidebar-search-row';
        row.title = item.name;
        row.innerHTML = buildSearchNameHtml(item.name);

        row.addEventListener('click', e => {
            e.preventDefault();
            searchInput.value = '';
            searchPanel.style.display = 'none';
            moveToItem(item);
        });

        return row;
    }

    function renderRecentItems() {
        if (!recentArea) return;

        recentArea.innerHTML = '<div class="sidebar-search-section-title">最近開いた項目</div>';

        const recentItems = getStorage(RECENT_KEY);

        if (recentItems.length === 0) {
            recentArea.innerHTML += '<div class="sidebar-search-row" title="最近開いた項目はありません">最近開いた項目はありません</div>';
            return;
        }

        recentItems.forEach(recent => {
            const matched = searchableItems.find(item => item.name === recent.name);
            if (matched) recentArea.appendChild(createResultRow(matched));
        });
    }

    const LAST_ACTIVE_KEY = 'lifeforce_sidebar_last_active_key';

    function activateSidebarByKey(key) {
        if (!key) return;

        const target = searchableItems.find(item => item.name === key);
        if (!target) return;

        document.querySelectorAll('.sidebar-item, .sidebar-subitem').forEach(el => {
            el.classList.remove('current');
        });

        document.querySelectorAll('.sidebar-submenu').forEach(menu => {
            menu.classList.remove('show');
        });

        document.querySelectorAll('.sidebar-toggle').forEach(toggle => {
            toggle.classList.remove('active');
        });

        target.target.classList.add('current');

        const submenu = target.target.closest('.sidebar-submenu');

        if (submenu) {
            submenu.classList.add('show');

            const parentToggle = submenu.previousElementSibling;
            if (parentToggle && parentToggle.classList.contains('sidebar-toggle')) {
                parentToggle.classList.add('active');
            }
        }
    }

    searchableItems.forEach(item => {
        item.target.addEventListener('click', () => {
            localStorage.setItem(LAST_ACTIVE_KEY, item.name);
            activateSidebarByKey(item.name);
        });
    });

    const currentSidebarKey =
        document.body.dataset.sidebarKey || localStorage.getItem(LAST_ACTIVE_KEY);

    activateSidebarByKey(currentSidebarKey);

    function getFavoriteItems() {
        return getStorage(FAVORITE_KEY);
    }

    function saveFavoriteItems(items) {
        setStorage(FAVORITE_KEY, items.slice(0, FAVORITE_LIMIT));
    }

    function isFavorite(name) {
        return getFavoriteItems().some(item => item.name === name);
    }

    function toggleFavorite(item) {
        const current = getFavoriteItems();

        let next;

        if (isFavorite(item.name)) {
            next = current.filter(row => row.name !== item.name);
        } else {
            next = [
                { name: item.name, info: item.info || '' },
                ...current
            ].slice(0, FAVORITE_LIMIT);
        }

        saveFavoriteItems(next);
        refreshFavoriteStars();
        renderFavoriteArea();
    }

    function createFavoriteArea() {
        if (!sidebarSearch) return;

        let area = document.getElementById('sidebarFavoriteArea');

        if (!area) {
            area = document.createElement('div');
            area.id = 'sidebarFavoriteArea';
            area.className = 'sidebar-favorites';
            sidebarSearch.insertAdjacentElement('afterend', area);
        }

        renderFavoriteArea();
    }

    function renderFavoriteArea() {
        const area = document.getElementById('sidebarFavoriteArea');
        if (!area) return;

        const favorites = getFavoriteItems();

        area.innerHTML = '<div class="sidebar-favorites-title">お気に入り</div>';

        if (favorites.length === 0) {
            area.style.display = 'none';
            return;
        }

        area.style.display = 'block';

        favorites.forEach(favorite => {
            const matched = searchableItems.find(item => item.name === favorite.name);
            if (!matched) return;

            const row = document.createElement('a');
            row.href = '#';
            row.className = 'sidebar-favorite-row';
            row.title = matched.name;

            const parts = matched.name.split('＞');
            const displayName = parts[parts.length - 1].trim();

            row.innerHTML = `
                <i class="fas fa-circle-minus sidebar-favorite-remove" title="お気に入りから外す"></i>
                <span class="sidebar-favorite-text">${displayName}</span>
            `;

            row.querySelector('.sidebar-favorite-remove').addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();

                const next = getFavoriteItems().filter(item => item.name !== matched.name);
                saveFavoriteItems(next);
                refreshFavoriteStars();
                renderFavoriteArea();
            });

            row.addEventListener('click', e => {
                e.preventDefault();
                moveToItem(matched);
            });

            area.appendChild(row);
        });
    }

    function addFavoriteStars() {
        searchableItems.forEach(item => {
            if (item.target.querySelector('.sidebar-favorite-toggle')) return;

            const star = document.createElement('i');
            star.className = 'fas fa-star sidebar-favorite-toggle';
            star.title = 'お気に入りに追加';

            star.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                toggleFavorite(item);
            });

            item.target.appendChild(star);
        });

        refreshFavoriteStars();
    }

    function refreshFavoriteStars() {
        searchableItems.forEach(item => {
            const star = item.target.querySelector('.sidebar-favorite-toggle');
            if (!star) return;

            if (isFavorite(item.name)) {
                star.classList.add('active');
                star.title = 'お気に入りから外す';
            } else {
                star.classList.remove('active');
                star.title = 'お気に入りに追加';
            }
        });
    }

    toggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const submenu = toggle.nextElementSibling;
            if (!submenu || !submenu.classList.contains('sidebar-submenu')) return;

            document.querySelectorAll('.sidebar-toggle').forEach(other => {
                if (other !== toggle) other.classList.remove('active');
            });

            document.querySelectorAll('.sidebar-submenu').forEach(otherMenu => {
                if (otherMenu !== submenu) otherMenu.classList.remove('show');
            });

            toggle.classList.toggle('active');
            submenu.classList.toggle('show');
        });
    });

    searchableItems.forEach(item => {
        item.target.addEventListener('click', () => {
            saveRecentItem(item);
        });
    });

    renderRecentItems();
    createFavoriteArea();
    addFavoriteStars();

    if (!searchInput || !searchPanel || !searchResults) return;

    searchInput.addEventListener('focus', () => {
        searchPanel.style.display = 'block';
        searchResults.innerHTML = '';
        if (recentArea) recentArea.style.display = 'block';
    });

    searchInput.addEventListener('input', () => {
        const keyword = searchInput.value.trim();
        searchResults.innerHTML = '';

        if (!keyword) {
            if (recentArea) recentArea.style.display = 'block';
            return;
        }

        if (recentArea) recentArea.style.display = 'none';

        const results = searchableItems
            .filter(item => item.name.includes(keyword))
            .slice(0, 12);

        if (results.length === 0) {
            searchResults.innerHTML = '<div class="sidebar-search-row" title="該当する画面がありません">該当する画面がありません</div>';
            return;
        }

        results.forEach(item => {
            searchResults.appendChild(createResultRow(item));
        });
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.sidebar-search')) {
            searchPanel.style.display = 'none';
        }
    });

    document.querySelectorAll('.sidebar-info').forEach(icon => {
        const parent = icon.closest('[data-info]');
        if (parent) icon.title = parent.dataset.info;
    });
});