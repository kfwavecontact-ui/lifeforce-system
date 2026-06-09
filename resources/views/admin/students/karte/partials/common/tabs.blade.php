<div class="karte-tabs">

     <a href="?tab=basic" class="karte-tab {{ request('tab', 'basic') === 'basic' ? 'active' : '' }}">
        <span>👤</span> 基本情報
    </a>

    <a href="?tab=routine" class="karte-tab {{ request('tab') === 'routine' ? 'active' : '' }}">
        <span>♻️</span> ルーティン
    </a>

    <a href="?tab=learning_plan" class="karte-tab {{ request('tab') === 'learning_plan' ? 'active' : '' }}">
        <span>📅</span> 計画学習
    </a>

    <a href="?tab=lesson" class="karte-tab {{ request('tab') === 'lesson' ? 'active' : '' }}">
        <span>🎓</span> 授業
    </a>

    <a href="?tab=growth" class="karte-tab {{ request('tab') === 'growth' ? 'active' : '' }}">
        <span>📈</span> 成長
    </a>

    <a href="?tab=event" class="karte-tab {{ request('tab') === 'event' ? 'active' : '' }}">
        <span>🎪</span> イベント
    </a>

    <a href="?tab=billing" class="karte-tab {{ request('tab') === 'billing' ? 'active' : '' }}">
        <span>🧾</span> 請求
    </a>

    <a href="?tab=contact" class="karte-tab {{ request('tab') === 'contact' ? 'active' : '' }}">
        <span>💬</span> 連絡・メモ
    </a>

</div>