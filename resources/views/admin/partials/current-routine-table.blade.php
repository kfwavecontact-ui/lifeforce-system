@php
    use Carbon\Carbon;

    $activeRoutines = $activeRoutines ?? collect();
    $todayStatuses = $todayStatuses ?? collect();
    $allStatuses = $allStatuses ?? collect();
    $calendarStatuses = $calendarStatuses ?? collect();
    $showTodayActual = $showTodayActual ?? false;
    $showActions = $showActions ?? false;
    $showRoutineActions = $showRoutineActions ?? true;
    $student = $student ?? null;

    $routineStatusLabel = static function ($status): array {
        return match ($status) {
            'completed' => ['○ 完了', 'success'],
            'in_progress', 'partial' => ['△ 途中終了', 'warning'],
            default => ['× 未着手', 'danger'],
        };
    };

    $routineDailyStats = static function ($item, $statuses): array {
        $start = $item && $item->start_date
            ? Carbon::parse($item->start_date)->startOfDay()
            : now()->startOfDay();
        $end = $item && $item->completed_at
            ? Carbon::parse($item->completed_at)->startOfDay()
            : now()->startOfDay();
        if ($end->lt($start)) $end = $start->copy();
        $elapsedDays = max(1, (int) $start->diffInDays($end) + 1);
        $rows = $statuses ?: collect();
        $workedDays = $rows->filter(function ($status) use ($start, $end) {
            $target = Carbon::parse($status->target_date)->startOfDay();
            return $target->betweenIncluded($start, $end)
                && in_array($status->status, ['completed', 'partial', 'in_progress'], true);
        })->pluck('target_date')->unique()->count();
        $achievedDays = $rows->filter(function ($status) use ($start, $end) {
            $target = Carbon::parse($status->target_date)->startOfDay();
            return $target->betweenIncluded($start, $end) && $status->status === 'completed';
        })->pluck('target_date')->unique()->count();
        return [$elapsedDays, min($workedDays, $elapsedDays), max(0, $elapsedDays - $workedDays), min($achievedDays, $elapsedDays)];
    };

    $routineMinimumDays = static function ($item): ?int {
        foreach (['minimum_days', 'min_days', 'required_days', 'target_days', 'minimum_achievement_days'] as $key) {
            if (isset($item->{$key}) && $item->{$key} !== null && $item->{$key} !== '') return (int) $item->{$key};
        }
        if (isset($item->routinePackageItem)) {
            foreach (['minimum_days', 'min_days', 'required_days', 'target_days', 'minimum_achievement_days'] as $key) {
                if (isset($item->routinePackageItem->{$key}) && $item->routinePackageItem->{$key} !== null && $item->routinePackageItem->{$key} !== '') return (int) $item->routinePackageItem->{$key};
            }
        }
        return null;
    };

    $routinePaceData = static function (int $elapsedDays, int $workedDays, ?int $minimumDays): array {
        if (!$minimumDays || $minimumDays <= 0) return ['available'=>false,'planned'=>null,'actual'=>null,'diff'=>null,'symbol'=>'—','color'=>'muted','label'=>'—'];
        $planned = min(100, (int) round(($elapsedDays / $minimumDays) * 100));
        $actual = min(100, (int) round(($workedDays / $minimumDays) * 100));
        $diff = $actual - $planned;
        if ($diff > 0) return ['available'=>true,'planned'=>$planned,'actual'=>$actual,'diff'=>$diff,'symbol'=>'↑','color'=>'success','label'=>'+'.$diff.'%'];
        if ($diff < 0) return ['available'=>true,'planned'=>$planned,'actual'=>$actual,'diff'=>$diff,'symbol'=>'↓','color'=>'danger','label'=>$diff.'%'];
        return ['available'=>true,'planned'=>$planned,'actual'=>$actual,'diff'=>0,'symbol'=>'→','color'=>'warning','label'=>'0%'];
    };

    $routineHistoryCells = static function ($item, $statuses, int $displayDays = 7): array {
        $start = $item && $item->start_date ? Carbon::parse($item->start_date)->startOfDay() : now()->startOfDay();
        $today = now()->startOfDay();
        if ($today->lt($start)) $today = $start->copy();
        $elapsed = max(1, (int) $start->diffInDays($today) + 1);
        $daysToShow = min($displayDays, $elapsed);
        $first = $today->copy()->subDays($daysToShow - 1);
        if ($first->lt($start)) $first = $start->copy();
        $rows = ($statuses ?: collect())->keyBy(fn($s) => Carbon::parse($s->target_date)->toDateString());
        $cells=[]; $worked=0;
        for ($date=$first->copy(); $date->lte($today); $date->addDay()) {
            $row=$rows->get($date->toDateString());
            $isWorked=in_array($row->status ?? 'not_started',['completed','partial','in_progress'],true);
            if ($isWorked) $worked++;
            $cells[]=['date'=>$date->format('Y/m/d'),'class'=>$isWorked?'worked':'missed'];
        }
        return [$cells,$worked,$elapsed];
    };

    $routineConsecutiveMissedDays = static function (array $historyCells): int {
        $count=0;
        foreach (array_reverse($historyCells) as $cell) {
            if (($cell['class'] ?? '') !== 'missed') break;
            $count++;
        }
        return $count;
    };

    $routineFollowStatus = static function ($minimumDays, $paceData, $todayStatus, $historyCells) use ($routineConsecutiveMissedDays): array {
        if (!$minimumDays || $minimumDays <= 0 || !($paceData['available'] ?? false)) return ['—','none','達成必要日数が未設定のため、状態判定の対象外です。'];
        $todayValue=$todayStatus->status ?? 'not_started';
        $diff=(int)($paceData['diff'] ?? 0);
        $missed=$routineConsecutiveMissedDays($historyCells);
        $diffLabel=($diff>0?'+':'').$diff.'%';
        if ($missed>=3) return ['🔴 フォロー急務','danger','直近'.$missed.'日連続で未実施のため、至急フォローが必要です。'];
        if ($diff<=-40) return ['🔴 フォロー急務','danger','学習ペース差分が'.$diffLabel.'のため、至急フォローが必要です。'];
        if ($missed>=2) return ['🟠 フォロー必要','warning','直近'.$missed.'日連続で未実施のため、先生の声かけが必要です。'];
        if ($diff<=-20) return ['🟠 フォロー必要','warning','学習ペース差分が'.$diffLabel.'のため、フォローが必要です。'];
        if ($todayValue==='not_started' && $diff<0) return ['🟡 要確認','caution','今日が未着手で、学習ペース差分が'.$diffLabel.'のため、様子確認が必要です。'];
        if ($todayValue==='completed' || $diff>=0) return ['🟢 順調','success','現在の学習ペースは順調です。'];
        return ['🟡 要確認','caution','学習ペース差分が'.$diffLabel.'のため、様子確認が必要です。'];
    };

    $routineIcon = static function (string $name, string $type='item'): string {
        if (str_contains($name,'脳')) return '🧠';
        if (str_contains($name,'将棋')) return '将';
        if (str_contains($name,'英検')) return '📖';
        if (str_contains($name,'数字')) return '数';
        if (str_contains($name,'不足')) return '🔍';
        if (str_contains($name,'シルエット')) return 'シ';
        if (str_contains($name,'九九')) return '九';
        if (str_contains($name,'リスニング')) return '🎧';
        return $type==='routine' ? 'R' : '●';
    };
    $calendarMark = static fn($status): array => match ($status) {
        'completed' => ['○','success'],
        'partial','in_progress' => ['△','warning'],
        default => ['×','danger'],
    };
    $japaneseDate = static function ($date): string {
        $c=Carbon::parse($date); $w=['日','月','火','水','木','金','土'];
        return $c->format('Y/m/d').'('.$w[$c->dayOfWeek].')';
    };
@endphp

<div class="routine-wrap routine-current-readonly">
    <div class="routine-routine-list">
        @forelse($activeRoutines as $routine)
            @php $items = ($routine->items ?? collect())->where('is_active', true)->values(); @endphp
            <div class="routine-routine-card">
                <div class="routine-routine-header">
                    <div class="routine-routine-title">
                        <span class="routine-package-icon">{{ $routineIcon($routine->name, 'routine') }}</span>
                        <div><div class="routine-routine-name">{{ $routine->name }}</div></div>
                    </div>
                </div>
                <div class="routine-table-wrap">
                    <table class="routine-table routine-main-table routine-card-table{{ (!$showTodayActual && !$showActions) ? ' routine-assignment-readonly-table' : '' }}">
                        <thead><tr>
                            <th class="routine-education-item-column">ルーティンアイテム</th>
                            @if($showTodayActual)<th>今日の実績</th>@endif
                            <th class="routine-education-today-status-column">今日のステータス</th>
                            <th class="routine-state-column">状態 <span class="routine-help">?</span></th>
                            <th>達成必要日数 <span class="routine-help">?</span></th>
                            <th class="routine-daily-minutes-column">1日の推奨学習時間</th>
                            <th>進捗率 <span class="routine-help">?</span></th>
                            <th>学習ペース <span class="routine-help">?</span></th>
                            <th>学習履歴 <span class="routine-help">?</span></th>
                            <th>補助教材</th>
                            @if($showActions)<th>アクション</th>@endif
                            <th>カレンダー</th>
                        </tr></thead>
                        <tbody>
                        @if($items->isEmpty())
                            <tr><td colspan="{{ 10 + ($showTodayActual ? 1 : 0) + ($showActions ? 1 : 0) }}" class="text-center py-4">このルーティンに表示できるアイテムはありません。</td></tr>
                        @else
                            @foreach($items as $item)
                                @php
                                    $todayStatus=$todayStatuses->get($item->id);
                                    $statuses=$allStatuses->get($item->id,collect());
                                    $calendarItemStatuses=$calendarStatuses->get($item->id,collect())->keyBy(fn($s)=>Carbon::parse($s->target_date)->toDateString());
                                    [$statusText,$statusColor]=$routineStatusLabel($todayStatus->status ?? 'not_started');
                                    [$elapsedDays,$workedDays,$restedDays,$achievedDays]=$routineDailyStats($item,$statuses);
                                    $minimumDays=$routineMinimumDays($item);
                                    $progressRate=$minimumDays ? min(100,(int)round(($workedDays/$minimumDays)*100)) : 0;
                                    $paceData=$routinePaceData($elapsedDays,$workedDays,$minimumDays);
                                    [$historyCells,$historyDisplayWorkedDays,$historyElapsedDays]=$routineHistoryCells($item,$statuses,7);
                                    [$followText,$followColor,$followReason]=$routineFollowStatus($minimumDays,$paceData,$todayStatus,$historyCells);
                                    $iconClass=match(($item->routine_content_id ?? 0)%4){1=>'routine-icon-blue',2=>'routine-icon-orange',3=>'routine-icon-green',default=>'routine-icon-purple'};
                                    $itemName=$item->item_name ?: optional($item->routineContent)->name ?: '名称未設定';
                                @endphp
                                <tr>
                                    <td class="routine-item-cell routine-education-item-column"><div class="routine-item-compact"><span class="routine-icon {{ $iconClass }}">{{ $routineIcon($itemName,'item') }}</span><div class="routine-item-main"><div class="routine-item-line1"><span class="routine-title">{{ $itemName }}</span></div><div class="routine-item-line2 routine-item-meta-line"><span class="routine-item-meta">{{ $item->tag ?? '-' }}</span><span class="routine-meta-separator">｜</span><span class="routine-start-date-mini">{{ $item->start_date ? Carbon::parse($item->start_date)->format('Y/m/d') : '-' }}開始</span><span class="routine-meta-separator">｜</span><button type="button" class="routine-content-link" data-routine-modal-target="#contentModal{{ $item->id }}" title="学習内容確認">📖</button></div></div></div></td>
                                    @if($showTodayActual)<td class="text-center">—</td>@endif
                                    <td class="text-center routine-education-today-status-column"><span class="routine-badge badge-{{ $statusColor }}">{{ $statusText }}</span></td>
                                    <td class="text-center routine-state-column"><span class="routine-hover routine-status-hover"><span class="routine-status-signal routine-status-{{ $followColor }}">{{ $followText }}</span><span class="routine-tooltip"><div class="routine-tooltip-title">状態：{{ $followText }}</div>{{ $followReason }}<br>@if($minimumDays && ($paceData['available'] ?? false))予定進捗：{{ $paceData['planned'] }}%<br>実績進捗：{{ $paceData['actual'] }}%<br>差分：{{ ($paceData['diff'] >= 0 ? '+' : '') . $paceData['diff'] }}%@else達成必要日数：未設定@endif</span></span></td>
                                    <td class="text-center"><span class="routine-hover"><span>{{ $minimumDays ? $minimumDays.'日' : '未設定' }}</span><span class="routine-tooltip"><div class="routine-tooltip-title">達成必要日数</div>@if($minimumDays)目標：{{ $minimumDays }}日<br>取り組んだ日数：{{ $workedDays }}日<br>残り必要日数：{{ max(0,$minimumDays-$workedDays) }}日@else達成必要日数が未設定です。@endif</span></span></td>
                                    <td class="text-center routine-daily-minutes-column">{{ isset($item->estimated_minutes) && $item->estimated_minutes !== null ? (int) $item->estimated_minutes . '分' : '未設定' }}</td>
                                    <td class="text-center">@if($minimumDays)<div class="routine-donut" style="--rate: {{ $progressRate }};"><span>{{ $progressRate }}%</span></div>@else—@endif</td>
                                    <td class="text-center"><span class="routine-pace routine-pace-{{ $paceData['color'] }}"><span>{{ $paceData['symbol'] }}</span><span>{{ $paceData['label'] }}</span></span></td>
                                    <td class="text-center"><span class="routine-history-wrap"><span class="routine-history-cells">@foreach($historyCells as $cell)<span class="routine-history-cell {{ $cell['class'] }}" title="{{ $cell['date'] }}"></span>@endforeach</span><span class="routine-history-summary">({{ $historyDisplayWorkedDays }}日 / {{ $historyElapsedDays }}日)</span></span></td>
                                    <td class="text-center"><div class="routine-material-stack">@if(optional($item->routineContent)->material_url)<a class="routine-btn routine-btn-cyan" href="{{ optional($item->routineContent)->material_url }}" target="_blank" rel="noopener noreferrer">教材</a>@else<span class="routine-btn routine-btn-disabled">教材</span>@endif @if(optional($item->routineContent)->video_url)<a class="routine-btn routine-btn-purple" href="{{ optional($item->routineContent)->video_url }}" target="_blank" rel="noopener noreferrer">動画</a>@else<span class="routine-btn routine-btn-disabled">動画</span>@endif</div></td>
                                    @if($showActions)<td class="text-center">—</td>@endif
                                    <td class="text-center"><button type="button" class="routine-btn routine-calendar-icon-btn" data-routine-modal-target="#calendarModal{{ $item->id }}" title="カレンダー">📅</button></td>
                                </tr>
                                <div class="routine-modal" id="contentModal{{ $item->id }}"><div class="routine-modal-dialog"><div class="routine-modal-content"><div class="routine-modal-header"><h5 class="routine-modal-title">学習内容：{{ $itemName }}</h5><button type="button" class="routine-modal-close" data-routine-modal-close>×</button></div><div class="routine-modal-body">{!! nl2br(e(optional($item->routineContent)->description ?? '学習内容は登録されていません。')) !!}</div></div></div></div>
                                <div class="routine-modal" id="calendarModal{{ $item->id }}"><div class="routine-modal-dialog"><div class="routine-modal-content"><div class="routine-modal-header"><h5 class="routine-modal-title">カレンダー：{{ $itemName }}</h5><button type="button" class="routine-modal-close" data-routine-modal-close>×</button></div><div class="routine-modal-body"><div class="calendar-grid">@php $calendarStart=$item->start_date?Carbon::parse($item->start_date)->startOfDay():now()->startOfDay(); $calendarEnd=$item->completed_at?Carbon::parse($item->completed_at)->startOfDay():now()->startOfDay(); @endphp @while($calendarStart->lte($calendarEnd)) @php $date=$calendarStart->toDateString(); $dayStatus=$calendarItemStatuses->get($date); [$mark,$markColor]=$calendarMark($dayStatus->status ?? 'not_started'); @endphp <div class="calendar-cell calendar-cell-wide"><div class="calendar-date-wide">{{ $japaneseDate($date) }}</div><div class="calendar-mark calendar-{{ $markColor }}">{{ $mark }}</div></div> @php $calendarStart->addDay(); @endphp @endwhile</div></div></div></div></div>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
                @if($showRoutineActions)
                    <div class="er-routine-actions">
                        <form method="POST" action="{{ route('admin.education.routines.state', $routine->id) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="stop"><button type="submit" class="er-small-button">停止</button></form>
                        <form method="POST" action="{{ route('admin.education.routines.state', $routine->id) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="complete"><button type="submit" class="er-small-button">完了</button></form>
                        <form method="POST" action="{{ route('admin.education.routines.cancel', $routine->id) }}" onsubmit="return confirm('この割当を取り消しますか？');">@csrf @method('DELETE')<button type="submit" class="er-danger-outline">割当取消</button></form>
                    </div>
                @endif
            </div>
        @empty
            <div class="routine-empty-card">現在表示できるルーティンはありません。</div>
        @endforelse
    </div>
</div>
