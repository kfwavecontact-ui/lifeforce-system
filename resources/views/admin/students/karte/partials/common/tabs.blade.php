<div class="karte-tabs">

    <a href="?tab=basic" class="karte-tab {{ request('tab', 'basic') === 'basic' ? 'active' : '' }}">
        基本情報
    </a>

    <a href="?tab=routine" class="karte-tab {{ request('tab') === 'routine' ? 'active' : '' }}">
        ルーティン
    </a>

    <a href="?tab=learning_plan" class="karte-tab {{ request('tab') === 'learning_plan' ? 'active' : '' }}">
        計画学習
    </a>

    <a href="?tab=lesson" class="karte-tab {{ request('tab') === 'lesson' ? 'active' : '' }}">
        授業
    </a>

    <a href="?tab=growth" class="karte-tab {{ request('tab') === 'growth' ? 'active' : '' }}">
        成長
    </a>
    
    <a href="?tab=event" class="karte-tab {{ request('tab') === 'event' ? 'active' : '' }}">
        イベント
    </a>

    <a href="?tab=billing" class="karte-tab {{ request('tab') === 'billing' ? 'active' : '' }}">
        請求
    </a>

    <a href="?tab=contact" class="karte-tab {{ request('tab') === 'contact' ? 'active' : '' }}">
        連絡・メモ
    </a>

</div>