<div class="info-card">
    <div class="card-header">
        <h3>保有称号</h3>
        <a href="#" class="card-link">すべて見る</a>
    </div>

    <div class="title-list">
        @forelse($student->studentTitles->take(3) as $studentTitle)
            @php
                $title = $studentTitle->title;

                $rarityClass = match($title->rarity ?? 'normal') {
                    'rare' => 'gold',
                    'normal' => 'silver',
                    default => 'bronze',
                };
            @endphp

            <div class="title-item {{ $rarityClass }}">
                <div class="title-icon">👑</div>

                <div class="title-name">
                    {{ $title->name ?? '未登録称号' }}
                </div>

                <div class="title-date">
                    {{ $studentTitle->acquired_at ? \Carbon\Carbon::parse($studentTitle->acquired_at)->format('Y/m/d') : '未登録' }}
                </div>

                @if($studentTitle->is_equipped)
                    <div class="title-equipped">装備中</div>
                @endif
            </div>
        @empty
            <div class="empty-state">
                保有称号はまだありません
            </div>
        @endforelse
    </div>
</div>