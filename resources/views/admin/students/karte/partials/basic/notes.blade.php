<div class="info-card">
    <div class="card-header">
        <h3>講師メモ（最新3件）</h3>
        <a href="#" class="card-link">すべて見る</a>
    </div>

    <div class="memo-list">
        @forelse($student->lessonNotes->take(3) as $note)
            <div class="memo-item">
                <div class="memo-date">
                    {{ $note->created_at ? $note->created_at->format('Y/m/d') : '日付未登録' }}
                </div>

                <div class="memo-author">
                    {{ $note->user->name ?? '担当者未登録' }}
                </div>

                <div class="memo-body">
                    {{ $note->body }}
                </div>
            </div>
        @empty
            <div class="empty-state">
                講師メモはまだありません
            </div>
        @endforelse
    </div>
</div>