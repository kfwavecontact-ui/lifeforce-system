<div id="today-routine" class="routine-today-card">

    <div class="routine-card-header">
        <h3>今日のルーティン</h3>
        <span>{{ now()->format('Y/m/d') }}</span>
    </div>

    <div class="routine-list">

        @php
            $today = now()->toDateString();
        @endphp

        @if($activeRoutine && $activeRoutine->items->count())

            @foreach($activeRoutine->items as $item)

                @php
                    $todayLog = \App\Models\RoutineCompletionLog::where('student_id', $student->id)
                        ->where('student_routine_item_id', $item->id)
                        ->where('completed_on', $today)
                        ->first();

                    $isCompleted = $todayLog && $todayLog->is_completed;

                    if ($item->required_minutes > 0) {
                        $targetText = $item->required_minutes . '分';
                    } elseif ($item->required_count > 0) {
                        $targetText = $item->required_count . '回';
                    } elseif ($item->required_accuracy > 0) {
                        $targetText = $item->required_accuracy . '%';
                    } else {
                        $targetText = '達成';
                    }
                @endphp

                <div class="routine-item">
                    <div class="routine-name">
                        📘 {{ $item->learningContent->name ?? 'ルーティン項目' }}
                    </div>

                    <div class="routine-target">
                        {{ $targetText }}
                    </div>

                    @if($isCompleted)
                        <form method="POST"
                              class="routine-ajax-form"
                              action="{{ route('admin.students.routine.cancel', $student) }}">
                            @csrf
                            @method('DELETE')

                            <input type="hidden"
                                   name="student_routine_item_id"
                                   value="{{ $item->id }}">

                            <button type="submit"
                                    class="routine-completed-badge">
                                達成済
                            </button>
                        </form>
                    @else
                        <form method="POST"
                              class="routine-ajax-form"
                              action="{{ route('admin.students.routine.complete', $student) }}">
                            @csrf

                            <input type="hidden"
                                   name="student_routine_id"
                                   value="{{ $activeRoutine->id }}">

                            <input type="hidden"
                                   name="student_routine_item_id"
                                   value="{{ $item->id }}">

                            <input type="hidden"
                                   name="point_amount"
                                   value="{{ $item->point_amount }}">

                            <button type="submit" class="btn-sm">
                                記録する
                            </button>
                        </form>
                    @endif
                </div>

            @endforeach

        @else
            <div class="empty-state">
                現在有効なルーティンは登録されていません
            </div>
        @endif

    </div>

</div>

<script>
document.querySelectorAll('.routine-ajax-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const isCancel = form.querySelector('input[name="_method"]');

        if (isCancel && !confirm('本日の記録を取り消しますか？')) {
            return;
        }

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new FormData(form)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('通信エラー');
            }
            return response.json();
        })
        .then(() => {
            location.reload();
        })
        .catch(() => {
            alert('処理に失敗しました。');
        });
    });
});
</script>