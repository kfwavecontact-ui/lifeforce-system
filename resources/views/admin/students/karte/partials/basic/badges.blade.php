<div class="info-card">
    <div class="card-header">
        <h3>獲得バッジ</h3>
        <a href="#" class="card-link">すべて見る</a>
    </div>

    <div class="badge-list">
        @forelse($student->studentBadges->take(5) as $studentBadge)
            @php
                $badge = $studentBadge->badge;
            @endphp

            <div class="badge-item">
                <div class="badge-icon">
    🏅
</div>

                <div class="badge-name">
                    {{ $badge->name ?? '未登録バッジ' }}
                </div>

                <div class="badge-date">
                    {{ $studentBadge->acquired_at ? \Carbon\Carbon::parse($studentBadge->acquired_at)->format('Y/m/d') : '未登録' }}
                </div>
            </div>
        @empty
            <div class="empty-state">
                獲得バッジはまだありません
            </div>
        @endforelse
    </div>
</div>