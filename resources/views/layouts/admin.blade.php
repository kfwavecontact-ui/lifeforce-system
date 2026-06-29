<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $title ?? 'LifeForce Core')</title>

    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/master.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/challenge-management.css') }}">

    @if(request()->routeIs('admin.operations.classroom-accounting.transactions.*'))
        <link rel="stylesheet" href="{{ asset('css/admin/account-transactions.css') }}">
    @endif

    @if(request()->routeIs('admin.operations.classroom-accounting.tuition-enrollment-sales.*'))
        <link rel="stylesheet" href="{{ asset('css/admin/tuition-enrollment-sales.css') }}">
    @endif

    @if(request()->routeIs('admin.operations.classroom-accounting.shop-sales.*'))
        <link rel="stylesheet" href="{{ asset('css/admin/shop-sales.css') }}">
    @endif
    

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    @stack('styles')

    @if(request()->routeIs('admin.operations.classroom-accounting.*'))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endif

</head>

@if(request()->routeIs('admin.system.badges*'))
    <link rel="stylesheet" href="{{ asset('css/admin/badge-management.css') }}">
@endif

@if(request()->routeIs('admin.system.titles*'))
    <link rel="stylesheet" href="{{ asset('css/admin/title-management.css') }}">
@endif


@if(request()->routeIs('admin.system.permissions*'))
    <link rel="stylesheet" href="{{ asset('css/admin/permission-management.css') }}">
@endif

<body data-sidebar-key="{{ $sidebarPageKey ?? '' }}">

<div class="layout">

    @include('admin.partials.sidebar')

    <main class="main">

        @include('admin.partials.header')

        <div class="page">
            @yield('content')
        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('.sidebar-toggle');
    const searchInput = document.getElementById('sidebarSearchInput');
    const searchPanel = document.getElementById('sidebarSearchPanel');
    const searchResults = document.getElementById('sidebarSearchResults');
    const recentArea = document.getElementById('sidebarRecentArea');
    const pinnedArea = document.getElementById('sidebarPinnedArea');

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const submenu = toggle.nextElementSibling;

            if (!submenu || !submenu.classList.contains('sidebar-submenu')) {
                return;
            }

            document.querySelectorAll('.sidebar-toggle').forEach(function (otherToggle) {
                if (otherToggle !== toggle) {
                    otherToggle.classList.remove('open');
                }
            });

            document.querySelectorAll('.sidebar-submenu').forEach(function (otherSubmenu) {
                if (otherSubmenu !== submenu) {
                    otherSubmenu.classList.remove('open');
                }
            });

            toggle.classList.toggle('open');
            submenu.classList.toggle('open');
        });
    });

    function openParentSubmenu(element) {
        const submenu = element.closest('.sidebar-submenu');

        if (!submenu) {
            return;
        }

        document.querySelectorAll('.sidebar-toggle').forEach(function (toggle) {
            toggle.classList.remove('open');
        });

        document.querySelectorAll('.sidebar-submenu').forEach(function (item) {
            item.classList.remove('open');
        });

        submenu.classList.add('open');

        const toggle = submenu.previousElementSibling;
        if (toggle && toggle.classList.contains('sidebar-toggle')) {
            toggle.classList.add('open');
        }
    }

    function highlightLastSegment(fullName) {
        const parts = fullName.split('＞').map(function (part) {
            return part.trim();
        });

        if (parts.length <= 1) {
            return '<span class="sidebar-search-strong">' + fullName + '</span>';
        }

        const last = parts.pop();
        const prefix = parts.join(' ＞ ');

        return '<span class="sidebar-search-muted">' + prefix + ' ＞ </span><span class="sidebar-search-strong">' + last + '</span>';
    }

    if (searchInput && searchPanel && searchResults) {
        const items = [];

        document.querySelectorAll('.sidebar-item, .sidebar-subitem').forEach(function (element) {
            const searchName = element.getAttribute('data-search-name');
            const label = searchName || element.innerText.replace(/\s+/g, ' ').trim();

            if (!label) {
                return;
            }

            items.push({
                label: label,
                element: element
            });
        });

        searchInput.addEventListener('focus', function () {
            searchPanel.classList.add('open');

            if (!searchInput.value.trim()) {
                searchResults.classList.remove('open');
                if (recentArea) recentArea.style.display = 'block';
                if (pinnedArea) pinnedArea.style.display = 'block';
            }
        });

        searchInput.addEventListener('input', function () {
            const keyword = searchInput.value.trim();

            searchResults.innerHTML = '';

            if (!keyword) {
                searchResults.classList.remove('open');
                if (recentArea) recentArea.style.display = 'block';
                if (pinnedArea) pinnedArea.style.display = 'block';
                return;
            }

            if (recentArea) recentArea.style.display = 'none';
            if (pinnedArea) pinnedArea.style.display = 'none';

            const matchedItems = items.filter(function (item) {
                return item.label.includes(keyword);
            }).slice(0, 12);

            if (matchedItems.length === 0) {
                searchResults.innerHTML = '<div class="sidebar-search-empty">該当する画面がありません</div>';
                searchResults.classList.add('open');
                return;
            }

            matchedItems.forEach(function (item) {
                const result = document.createElement('a');
                result.href = '#';
                result.className = 'sidebar-search-result';
                result.innerHTML = highlightLastSegment(item.label);

                result.addEventListener('click', function (event) {
                    event.preventDefault();

                    searchInput.value = '';
                    searchPanel.classList.remove('open');

                    document.querySelectorAll('.sidebar-item, .sidebar-subitem').forEach(function (menuItem) {
                        menuItem.classList.remove('active');
                    });

                    openParentSubmenu(item.element);

                    item.element.classList.add('active');

                    setTimeout(function () {
                        item.element.classList.remove('active');
                    }, 1200);
                });

                searchResults.appendChild(result);
            });

            searchResults.classList.add('open');
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.sidebar-search')) {
                searchPanel.classList.remove('open');
            }
        });
    }
});
</script>
<script src="{{ asset('js/admin/sidebar.js') }}"></script>
<script src="{{ asset('js/admin/header.js') }}"></script>
<script src="{{ asset('js/admin/master.js') }}"></script>
@if(request()->routeIs('admin.system.challenges*'))
    <script src="{{ asset('js/admin/challenge-management.js') }}"></script>
@endif
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

@if(request()->routeIs('admin.system.badges*'))
    <script src="{{ asset('js/admin/badge-management.js') }}"></script>
@endif

@if(request()->routeIs('admin.system.titles*'))
    <script src="{{ asset('js/admin/title-management.js') }}"></script>
@endif

@if(request()->routeIs('admin.operations.classroom-accounting.transactions.*'))
    <script src="{{ asset('js/admin/account-transactions.js') }}"></script>
@endif

@if(request()->routeIs('admin.operations.classroom-accounting.tuition-enrollment-sales.*'))
    <script src="{{ asset('js/admin/tuition-enrollment-sales.js') }}"></script>
@endif

@if(request()->routeIs('admin.operations.classroom-accounting.shop-sales.*'))
    <script src="{{ asset('js/admin/shop-sales.js') }}"></script>
@endif




</body>
</html>