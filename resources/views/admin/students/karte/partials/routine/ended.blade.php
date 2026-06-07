<div class="ended-routine-card">

    <div class="routine-card-header">
        <h3>終了したルーティン一覧</h3>
    </div>

    @if($endedRoutines && $endedRoutines->count())

        <div class="ended-routine-table-wrap">
            <table class="ended-routine-table">
                <thead>
                    <tr>
                        <th>結果</th>
                        <th>ルーティン名</th>
                        <th>期間</th>
                        <th>達成数</th>
                        <th>達成率</th>
                        <th>自己評価</th>
                        <th>自己評価コメント</th>
                        <th>詳細</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($endedRoutines as $routine)

                        @php
                            $totalCount = 0;
                            $completedCount = 0;

                            foreach ($routine->items as $item) {
                                if ($routine->start_date && $routine->end_date) {
                                    $start = \Carbon\Carbon::parse($routine->start_date);
                                    $end = \Carbon\Carbon::parse($routine->end_date);

                                    $targetDays = $start->diffInDays($end) + 1;
                                    $totalCount += $targetDays;

                                    $completedCount += \App\Models\RoutineCompletionLog::where('student_routine_item_id', $item->id)
                                        ->where('is_completed', true)
                                        ->whereBetween('completed_on', [
                                            $start->toDateString(),
                                            $end->toDateString(),
                                        ])
                                        ->count();
                                }
                            }

                            $achievementRate = $totalCount > 0
                                ? round(($completedCount / $totalCount) * 100, 1)
                                : 0;

                            if ($achievementRate >= 95) {
                                $resultLabel = '優秀達成';
                                $resultClass = 'excellent';
                            } elseif ($achievementRate >= 80) {
                                $resultLabel = '達成';
                                $resultClass = 'success';
                            } elseif ($achievementRate >= 60) {
                                $resultLabel = '一部達成';
                                $resultClass = 'warning';
                            } else {
                                $resultLabel = '未達成';
                                $resultClass = 'danger';
                            }

                            $score = $routine->self_evaluation_score;
                            $stars = $score
                                ? str_repeat('★', $score) . str_repeat('☆', 5 - $score)
                                : '未登録';
                        @endphp

                        <tr>
                            <td>
                                <span class="ended-routine-result {{ $resultClass }}">
                                    {{ $resultLabel }}
                                </span>
                            </td>

                            <td>
                                <strong>{{ $routine->title }}</strong>
                                @if($routine->description)
                                    <div class="ended-routine-description">
                                        {{ $routine->description }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{ $routine->start_date }}<br>
                                ～<br>
                                {{ $routine->end_date ?? '未設定' }}
                            </td>

                            <td>
                                {{ $completedCount }}/{{ $totalCount }}
                            </td>

                            <td>
                                {{ $achievementRate }}%
                            </td>

                            <td>
                                {{ $stars }} @if($score)({{ $score }})@endif
                            </td>

                            <td>
                                {{ $routine->self_evaluation_comment ?? '未登録' }}
                            </td>
                            <td>
                                <button type="button"
                                        class="btn-sm"
                                        onclick="document.getElementById('ended-routine-modal-{{ $routine->id }}').classList.add('show')">
                                    詳細
                                </button>
                            </td>
                        </tr>
                        <div id="ended-routine-modal-{{ $routine->id }}" class="routine-modal">
                            <div class="routine-modal-content">

                                <div class="routine-modal-header">
                                    <h3>{{ $routine->title }}</h3>

                                    <button type="button"
                                            class="routine-modal-close"
                                            onclick="document.getElementById('ended-routine-modal-{{ $routine->id }}').classList.remove('show')">
                                        ×
                                    </button>
                                </div>

                                <div class="routine-modal-body">

                                    <div class="routine-modal-row">
                                        <span>期間</span>
                                        <strong>{{ $routine->start_date }} ～ {{ $routine->end_date }}</strong>
                                    </div>

                                    <div class="routine-modal-row">
                                        <span>達成数</span>
                                        <strong>{{ $completedCount }}/{{ $totalCount }}</strong>
                                    </div>

                                    <div class="routine-modal-row">
                                        <span>達成率</span>
                                        <strong>{{ $achievementRate }}%</strong>
                                    </div>

                                    <div class="routine-modal-row">
                                        <span>結果</span>
                                        <strong>{{ $resultLabel }}</strong>
                                    </div>

                                    <div class="routine-modal-row">
                                        <span>自己評価</span>
                                        <strong>{{ $stars }} @if($score)({{ $score }})@endif</strong>
                                    </div>

                                    <div class="routine-modal-comment">
                                        <span>自己評価コメント</span>
                                        <p>{{ $routine->self_evaluation_comment ?? '未登録' }}</p>
                                    </div>

                                </div>

                            </div>
                        </div>
                    @endforeach
                </tbody>
            </table>
        </div>

    @else

        <div class="empty-state">
            終了したルーティンはありません
        </div>

    @endif

</div>