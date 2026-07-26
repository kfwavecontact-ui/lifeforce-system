<nav class="er-tabs" aria-label="ルーティン画面">
    <a class="{{ request()->routeIs('admin.education.routines.index') ? 'active' : '' }}" href="{{ route('admin.education.routines.index') }}">ルーティン割当</a>
    <a class="{{ request()->routeIs('admin.education.routines.history') ? 'active' : '' }}" href="{{ route('admin.education.routines.history') }}">割当済ルーティン状況</a>
</nav>
