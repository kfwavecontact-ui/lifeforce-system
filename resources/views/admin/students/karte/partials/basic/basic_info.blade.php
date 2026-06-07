<div class="info-card">
    <h3>基本情報</h3>

    <div class="info-row">
        <span>性別</span>
        <strong>{{ $student->gender ?? '未登録' }}</strong>
    </div>

    <div class="info-row">
        <span>生年月日</span>
        <strong>{{ $student->birthday ?? '未登録' }}</strong>
    </div>

    <div class="info-row">
        <span>住所</span>
        <strong>未登録</strong>
    </div>

    <div class="info-row">
        <span>電話番号</span>
        <strong>未登録</strong>
    </div>

    <div class="info-row">
        <span>メール</span>
        <strong>{{ $student->user->email ?? '未登録' }}</strong>
    </div>

    <div class="info-row">
        <span>学校名</span>
        <strong>{{ $student->school_name ?? '未登録' }}</strong>
    </div>

    <div class="info-row">
        <span>入会コース</span>
        <strong>未登録</strong>
    </div>

    <div class="info-row">
        <span>通塾曜日</span>
        <strong>{{ $student->commute_days ?? '未登録' }}</strong>
    </div>

    <div class="info-row">
        <span>備考</span>
        <strong>{!! nl2br(e($student->remarks ?? '未登録')) !!}</strong>
    </div>
</div>

<div class="info-card">
    <div class="card-header">
        <h3>保護者情報</h3>
        <button class="btn-sm">保護者詳細</button>
    </div>

    <div class="karte-parent-list">
        @forelse($student->parents as $parent)
            <div class="karte-parent-card">
                <div class="karte-parent-header">
                    <div>
                        <strong>
                            {{ $parent->pivot->relationship === 'mother' ? '母' : ($parent->pivot->relationship === 'father' ? '父' : $parent->pivot->relationship) }}：
                            {{ $parent->last_name }} {{ $parent->first_name }}
                        </strong>

                        @if($parent->pivot->is_primary)
                            <span class="karte-primary-badge">主保護者</span>
                        @endif
                    </div>
                </div>

                <div class="karte-parent-detail">
                    <div>
                        <span>電話番号</span>
                        <b>{{ $parent->phone_number }}</b>
                    </div>

                    <div>
                        <span>住所</span>
                        <b>{{ $parent->address }}</b>
                    </div>

                    <div>
                        <span>職業</span>
                        <b>{{ $parent->occupation }}</b>
                    </div>
                </div>
            </div>
        @empty
            <div class="empty-state">保護者情報が登録されていません</div>
        @endforelse
    </div>
</div>

<div class="info-card">
    <h3>直近の授業・出席状況</h3>

        @forelse($student->lessonReservations->take(5) as $reservation)
        @php
            $session = $reservation->lessonSession;

            $statusLabel = match($reservation->reservation_status) {
                'reserved' => '予約済',
                'attended' => '出席',
                'absent' => '欠席',
                'cancelled' => 'キャンセル',
                default => $reservation->reservation_status,
            };

            $statusClass = match($reservation->reservation_status) {
                'reserved' => 'status-reserved',
                'attended' => 'status-ok',
                'absent' => 'status-ng',
                'cancelled' => 'status-ng',
                default => 'status-reserved',
            };
        @endphp

        <div class="lesson-row">

            <div class="lesson-info">
                <div class="lesson-date">
                    {{ optional($session)->lesson_date }}
                </div>

                <div class="lesson-title">
                    {{ optional($session)->title }}
                </div>
            </div>

            <span class="{{ $statusClass }}">
                {{ $statusLabel }}
            </span>

        </div>
    @empty
        <div class="empty-state">
            直近の授業予約はありません
        </div>
    @endforelse
</div>