@php
    $weekLabels = [
        'Mon' => '月',
        'Tue' => '火',
        'Wed' => '水',
        'Thu' => '木',
        'Fri' => '金',
        'Sat' => '土',
        'Sun' => '日',
    ];
@endphp

<div class="routine-table-wrap">
    <table class="routine-table">
        <thead>
            <tr>
                <th>項目</th>
                <th>達成数</th>
                <th>達成率</th>
                <th>状態</th>
                <th>連続状況</th>

                @foreach($routinePeriods as $date)
                    <th class="{{ $date->isToday() ? 'today-column' : '' }}">
                        {{ $date->format('Y/m/d') }}
                        <br>
                        <span class="routine-date-week">
                            （{{ $weekLabels[$date->format('D')] ?? '' }}）
                        </span>
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @if($activeRoutine && $activeRoutine->items->count())

                @foreach($activeRoutine->items as $item)

                    @php
                        $periodDays = collect($routinePeriods)
                            ->filter(function ($date) use ($activeRoutine) {
                                $start = $activeRoutine?->start_date
                                    ? \Carbon\Carbon::parse($activeRoutine->start_date)
                                    : null;

                                $end = $activeRoutine?->end_date
                                    ? \Carbon\Carbon::parse($activeRoutine->end_date)
                                    : null;

                                return
                                    (!$start || $date->gte($start))
                                    &&
                                    (!$end || $date->lte($end))
                                    &&
                                    $date->lte(now());
                            });

                        $targetCount = $periodDays->count();

                        $completedCount = $periodDays->filter(function ($date) use ($item, $routineLogs) {
                            $key = $item->id . '_' . $date->toDateString();
                            $logs = $routineLogs[$key] ?? collect();

                            return $logs->contains('is_completed', true);
                        })->count();

                        $achievementRate =
                            $targetCount > 0
                                ? round(($completedCount / $targetCount) * 100, 1)
                                : 0;

                        if ($achievementRate >= 90) {
                            $statusLabel = '順調';
                            $statusClass = 'good';
                        } elseif ($achievementRate >= 70) {
                            $statusLabel = 'おおむね順調';
                            $statusClass = 'normal';
                        } elseif ($achievementRate >= 50) {
                            $statusLabel = 'フォロー必要';
                            $statusClass = 'warning';
                        } else {
                            $statusLabel = 'フォロー急務';
                            $statusClass = 'danger';
                        }

                        $streakDays = 0;
                        $currentDate = now()->copy();

                        while (true) {
                            $key = $item->id . '_' . $currentDate->toDateString();
                            $logs = $routineLogs[$key] ?? collect();

                            if ($logs->contains('is_completed', true)) {
                                $streakDays++;
                                $currentDate->subDay();
                            } else {
                                break;
                            }
                        }

                        $yesterdayKey = $item->id . '_' . now()->subDay()->toDateString();
                        $yesterdayLogs = $routineLogs[$yesterdayKey] ?? collect();

                        $isContinuing =
                            $streakDays > 0
                            ||
                            $yesterdayLogs->contains('is_completed', true);

                        $streakLabel =
                            $streakDays > 0
                                ? $streakDays . '日'
                                : '0日';

                        if ($streakDays > 0) {
                            $streakLabel .= '（継続中）';
                        }
                    @endphp

                    <tr>
                        <td>
                            {{ $item->learningContent->name ?? 'ルーティン項目' }}
                        </td>

                        <td>
                            {{ $completedCount }}/{{ $targetCount }}
                        </td>

                        <td>
                            {{ $achievementRate }}%
                        </td>

                        <td>
                            <span class="routine-status-tag {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        <td>
                            {{ $streakLabel }}
                        </td>

                        @foreach($routinePeriods as $date)
                            @php
                                $key = $item->id . '_' . $date->toDateString();
                                $logs = $routineLogs[$key] ?? collect();
                                $isCompleted = $logs->contains('is_completed', true);

                                $routineStart = $activeRoutine?->start_date
                                    ? \Carbon\Carbon::parse($activeRoutine->start_date)->startOfDay()
                                    : null;

                                $routineEnd = $activeRoutine?->end_date
                                    ? \Carbon\Carbon::parse($activeRoutine->end_date)->endOfDay()
                                    : null;

                                $isWithinRoutinePeriod =
                                    (!$routineStart || $date->gte($routineStart))
                                    &&
                                    (!$routineEnd || $date->lte($routineEnd));
                            @endphp

                            <td class="{{ $date->isToday() ? 'today-column' : '' }}">
                                @if($isCompleted)
                                    <span class="routine-mark success">○</span>
                                @else
                                    @if($isWithinRoutinePeriod && $date->isPast() && !$date->isToday())
                                        <span class="routine-mark danger">×</span>
                                    @else
                                        <span class="routine-mark empty"></span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

            @else
                <tr>
                    <td colspan="{{ 5 + $routinePeriods->count() }}">
                        今日のルーティンは登録されていません
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>