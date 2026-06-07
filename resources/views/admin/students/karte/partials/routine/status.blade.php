<div id="routine-status" class="routine-status-card">

    <div class="routine-card-header">
        <div>
            <h3>現在進行中のルーティン一覧</h3>

            <div class="routine-legend">
                <span><span class="routine-mark success">○</span> 達成</span>
                <span><span class="routine-mark danger">×</span> 未達成</span>
                <span><span class="routine-blank"></span> 未記録</span>
            </div>
        </div>

        <div class="routine-header-actions">
            @if($activeRoutine)
                <button type="button"
                        class="btn-finish-routine"
                        onclick="document.getElementById('finish-routine-modal').classList.add('show')">
                    ルーティン完了
                </button>
            @endif

            <form method="GET" class="routine-filter-form">
                <input type="hidden" name="tab" value="routine">

                <select name="period" class="routine-filter">
                    <option value="this_week" {{ ($period ?? 'this_week') === 'this_week' ? 'selected' : '' }}>今週の実施状況</option>
                    <option value="this_month" {{ ($period ?? '') === 'this_month' ? 'selected' : '' }}>今月の実施状況</option>
                    <option value="last_month" {{ ($period ?? '') === 'last_month' ? 'selected' : '' }}>先月の実施状況</option>
                    <option value="last_3_months" {{ ($period ?? '') === 'last_3_months' ? 'selected' : '' }}>過去3か月</option>
                    <option value="last_6_months" {{ ($period ?? '') === 'last_6_months' ? 'selected' : '' }}>過去6か月</option>
                    <option value="last_year" {{ ($period ?? '') === 'last_year' ? 'selected' : '' }}>過去1年</option>
                    <option value="all" {{ ($period ?? '') === 'all' ? 'selected' : '' }}>全期間</option>
                </select>
            </form>
        </div>
    </div>

    <div id="routine-status-table-area">
        @include('admin.students.karte.partials.routine.status_table')
    </div>

</div>

@if($activeRoutine)
    <div id="finish-routine-modal" class="routine-modal">
        <div class="routine-modal-content">

            <div class="routine-modal-header">
                <h3>ルーティン完了</h3>

                <button type="button"
                        class="routine-modal-close"
                        onclick="document.getElementById('finish-routine-modal').classList.remove('show')">
                    ×
                </button>
            </div>

            <form method="POST"
                  action="{{ route('admin.students.routine.finish', $student) }}">
                @csrf

                <input type="hidden"
                       name="student_routine_id"
                       value="{{ $activeRoutine->id }}">

                <div class="routine-form-group">
                    <label>対象ルーティン</label>
                    <div class="routine-form-readonly">
                        {{ $activeRoutine->title }}
                    </div>
                </div>

                <div class="routine-form-group">
                    <label>終了理由</label>
                    <select name="completion_reason" required>
                        <option value="">選択してください</option>
                        <option value="goal_achieved">目標達成</option>
                        <option value="period_ended">期間満了</option>
                        <option value="cancelled">途中終了</option>
                    </select>
                </div>

                <div class="routine-form-group">
                    <label>自己評価（5段階）</label>
                    <select name="self_evaluation_score">
                        <option value="">未選択</option>
                        <option value="5">★★★★★（5）</option>
                        <option value="4">★★★★☆（4）</option>
                        <option value="3">★★★☆☆（3）</option>
                        <option value="2">★★☆☆☆（2）</option>
                        <option value="1">★☆☆☆☆（1）</option>
                    </select>
                </div>

                <div class="routine-form-group">
                    <label>自己評価コメント</label>
                    <textarea name="self_evaluation_comment"
                              rows="3"
                              placeholder="生徒本人の振り返りを入力します"></textarea>
                </div>

                <div class="routine-form-group">
                    <label>講師コメント</label>
                    <textarea name="teacher_comment"
                              rows="3"
                              placeholder="講師・教室長からのコメントを入力します"></textarea>
                </div>

                <div class="routine-modal-actions">
                    <button type="button"
                            class="btn-secondary"
                            onclick="document.getElementById('finish-routine-modal').classList.remove('show')">
                        キャンセル
                    </button>

                    <button type="submit"
                            class="btn-primary"
                            onclick="return confirm('このルーティンを完了しますか？')">
                        完了する
                    </button>
                </div>
            </form>

        </div>
    </div>
@endif

<script>
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('routine-filter')) {
        return;
    }

    const period = e.target.value;
    const url = new URL(window.location.href);

    url.searchParams.set('tab', 'routine');
    url.searchParams.set('period', period);

    fetch(url.toString(), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('通信エラー');
        }

        return response.text();
    })
    .then(function(html) {
        const tableArea = document.getElementById('routine-status-table-area');

        if (tableArea) {
            tableArea.innerHTML = html;
            history.replaceState(null, '', url.toString());
        }
    })
    .catch(function() {
        alert('実施状況の切り替えに失敗しました。');
    });
});
</script>