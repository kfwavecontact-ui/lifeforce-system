@php
    use Carbon\Carbon;

    function historyJapaneseDate($date) {
        $carbon = Carbon::parse($date);
        $weekdays = ['日', '月', '火', '水', '金', '土'];
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        return $carbon->format('Y/m/d') . '(' . $weekdays[$carbon->dayOfWeek] . ')';
    }

    function historyDailyStats($item, $statuses) {
        $start = $item && $item->start_date
            ? Carbon::parse($item->start_date)->startOfDay()
            : now()->startOfDay();

        $end = $item && $item->completed_at
            ? Carbon::parse($item->completed_at)->startOfDay()
            : now()->startOfDay();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $elapsedDays = max(1, (int) $start->diffInDays($end) + 1);
        $statusRows = $statuses ?: collect();

        $workedDays = $statusRows
            ->filter(function ($status) use ($start, $end) {
                $targetDate = Carbon::parse($status->target_date)->startOfDay();
                return $targetDate->betweenIncluded($start, $end)
                    && in_array($status->status, ['completed', 'partial', 'in_progress'], true);
            })
            ->pluck('target_date')
            ->unique()
            ->count();

        $workedDays = min($workedDays, $elapsedDays);
        $restedDays = max(0, $elapsedDays - $workedDays);

        return [$elapsedDays, $workedDays, $restedDays];
    }

    function historyProgressRate($workedDays, $requiredDays) {
        if (!$requiredDays || $requiredDays <= 0) {
            return null;
        }

        return min(100, (int) round(($workedDays / $requiredDays) * 100));
    }

    function historyCells($item, $statuses, $displayDays = 7) {
        $start = $item && $item->start_date
            ? Carbon::parse($item->start_date)->startOfDay()
            : now()->startOfDay();

        $end = $item && $item->completed_at
            ? Carbon::parse($item->completed_at)->startOfDay()
            : now()->startOfDay();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $elapsed = max(1, (int) $start->diffInDays($end) + 1);
        $daysToShow = min($displayDays, $elapsed);
        $first = $end->copy()->subDays($daysToShow - 1);
        if ($first->lt($start)) {
            $first = $start->copy();
        }

        $statusRows = $statuses ? $statuses->keyBy(fn($s) => Carbon::parse($s->target_date)->toDateString()) : collect();
        $cells = [];
        $worked = 0;

        for ($date = $first->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $statusRows->get($key);
            $status = $row->status ?? 'not_started';
            $isWorked = in_array($status, ['completed', 'partial', 'in_progress'], true);

            if ($isWorked) {
                $worked++;
            }

            $cells[] = [
                'date' => historyJapaneseDate($key),
                'class' => $isWorked ? 'worked' : 'missed',
            ];
        }

        return [$cells, $worked, $elapsed];
    }

    function historyIcon($name) {
        if (str_contains($name, '脳')) return '🧠';
        if (str_contains($name, '将棋')) return '将';
        if (str_contains($name, '英検')) return '📖';
        if (str_contains($name, '数字')) return '数';
        if (str_contains($name, '不足')) return '🔍';
        if (str_contains($name, 'シルエット')) return 'シ';
        if (str_contains($name, '九九')) return '九';
        if (str_contains($name, 'リスニング')) return '🎧';
        return '●';
    }
@endphp

<style>
    .history-wrap { font-size: 13px; color: #0f172a; padding: 16px; }
    .history-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
    .history-title { font-size:20px; font-weight:900; color:#0f376d; }
    .history-card { background:#fff; border:1px solid #d8e3f0; border-radius:10px; overflow:visible; }
    .history-table-wrap { overflow-x:auto; overflow-y:visible; }
    .history-table { width:100%; border-collapse:collapse; min-width:1320px; table-layout:fixed; }
    .history-table th { background:#f4f7fb; border:1px solid #dfe7f1; padding:10px 8px; white-space:nowrap; font-size:12px; }
    .history-table td { border:1px solid #dfe7f1; padding:8px; vertical-align:middle; }
    .history-table th:nth-child(1), .history-table td:nth-child(1) { width:24%; min-width:245px; text-align:center; }
    .history-table td:nth-child(1) { text-align:left; }
    .history-table th:nth-child(2), .history-table td:nth-child(2) { width:7%; min-width:86px; }
    .history-table th:nth-child(3), .history-table td:nth-child(3) { width:8%; min-width:96px; }
    .history-table th:nth-child(4), .history-table td:nth-child(4) { width:8%; min-width:96px; }
    .history-table th:nth-child(5), .history-table td:nth-child(5) { width:8%; min-width:96px; }
    .history-table th:nth-child(6), .history-table td:nth-child(6) { width:7%; min-width:86px; }
    .history-table th:nth-child(7), .history-table td:nth-child(7) { width:11%; min-width:132px; }
    .history-table th:nth-child(8), .history-table td:nth-child(8) { width:8%; min-width:96px; }
    .history-table th:nth-child(9), .history-table td:nth-child(9) { width:19%; min-width:240px; }
    .history-btn { display:inline-block; border:1px solid #b8cdf5; color:#2563eb; background:#fff; border-radius:6px; padding:6px 10px; font-weight:800; text-decoration:none; cursor:pointer; }
    .history-back { background:#2563eb; color:#fff; border:0; }
    .text-center { text-align:center; }
    .history-item-box { display:flex; align-items:center; gap:8px; min-width:0; }
    .history-icon { width:30px; height:30px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; flex:0 0 30px; background:#eff6ff; color:#2563eb; font-weight:900; }
    .history-title-text { font-weight:900; line-height:1.25; color:#0f172a; }
    .history-sub { margin-top:3px; font-size:11px; color:#64748b; font-weight:700; }
    .history-result { display:inline-flex; align-items:center; justify-content:center; gap:4px; font-weight:900; white-space:nowrap; }
    .history-result.success { color:#15803d; }
    .history-result.danger { color:#dc2626; }
    .history-result.muted { color:#64748b; }
    .history-donut { --rate:0; width:46px; height:46px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; position:relative; background:conic-gradient(#16a34a calc(var(--rate) * 1%), #e5e7eb 0); }
    .history-donut.rate-success { background:conic-gradient(#16a34a calc(var(--rate) * 1%), #e5e7eb 0); }
    .history-donut.rate-warning { background:conic-gradient(#f59e0b calc(var(--rate) * 1%), #e5e7eb 0); }
    .history-donut.rate-danger { background:conic-gradient(#ef4444 calc(var(--rate) * 1%), #e5e7eb 0); }
    .history-donut::before { content:""; position:absolute; width:32px; height:32px; border-radius:50%; background:#fff; }
    .history-donut span { position:relative; z-index:1; font-size:10px; font-weight:900; color:#0f172a; }
    .history-hover { position:relative; display:inline-flex; align-items:center; justify-content:center; }
    .history-tooltip { display:none; position:absolute; z-index:9999; left:50%; top:calc(100% + 8px); transform:translateX(-50%); min-width:210px; max-width:300px; padding:10px 12px; border:1px solid #cfe0f5; border-radius:10px; background:#fff; box-shadow:0 16px 36px rgba(15,23,42,.16); color:#0f172a; font-size:11px; font-weight:700; line-height:1.7; text-align:left; white-space:normal; }
    .history-hover:hover .history-tooltip { display:block; }
    .history-tooltip-title { font-size:12px; font-weight:900; color:#0f376d; margin-bottom:5px; }
    .history-cells { display:inline-flex; align-items:center; justify-content:center; gap:4px; white-space:nowrap; }
    .history-cell { width:13px; height:13px; border-radius:4px; border:1px solid #dbe3ee; display:inline-block; }
    .history-cell.worked { background:#22c55e; border-color:#22c55e; }
    .history-cell.missed { background:#ef4444; border-color:#ef4444; }
    .history-summary { margin-top:5px; color:#334155; font-size:11px; font-weight:900; line-height:1; }
    .history-stars { display:inline-flex; align-items:center; gap:1px; font-size:14px; font-weight:900; white-space:nowrap; }
    .history-stars .star-on { color:#f59e0b; }
    .history-stars .star-off { color:#cbd5e1; }
    .history-score { margin-left:4px; color:#475569; font-size:11px; font-weight:800; }
    .history-comment { text-align:left; font-size:12px; line-height:1.45; color:#334155; word-break:break-word; }
    .history-comment-combined { display:flex; flex-direction:column; gap:6px; max-width:100%; text-align:left; }
    .history-comment-line { display:grid; grid-template-columns:42px 1fr; gap:6px; align-items:start; min-width:0; }
    .history-comment-label { display:inline-flex; align-items:center; justify-content:center; border-radius:6px; padding:2px 6px; font-size:11px; font-weight:900; line-height:1.3; white-space:nowrap; }
    .history-comment-label.self { background:#dbeafe; color:#1d4ed8; }
    .history-comment-label.teacher { background:#dcfce7; color:#15803d; }
    .history-comment-text { min-width:0; color:#334155; font-size:12px; font-weight:700; line-height:1.45; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; word-break:break-word; }

    .history-tooltip {
        display:none !important;
    }
    .history-floating-tooltip {
        position:fixed;
        z-index:99999;
        min-width:260px;
        max-width:420px;
        padding:12px 14px;
        border:1px solid #cfe0f5;
        border-radius:10px;
        background:#fff;
        box-shadow:0 16px 32px rgba(15, 55, 109, .18);
        color:#0f172a;
        font-size:12px;
        font-weight:700;
        line-height:1.7;
        text-align:left;
        white-space:normal;
        pointer-events:none;
    }
    .history-floating-tooltip strong {
        display:inline-block;
        color:#0f376d;
        font-size:12px;
        font-weight:900;
        margin-bottom:2px;
    }

</style>

<div class="history-wrap">
    <div class="history-header">
        <div>
            <div class="history-title">終了したルーティンアイテム一覧</div>
            <div>{{ $student->user->name ?? '生徒' }}</div>
        </div>

        <a href="{{ route('admin.students.karte.show', ['student' => $student->id, 'tab' => 'routine']) }}"
           class="history-btn history-back">
            ルーティンタブへ戻る
        </a>
    </div>

    <div class="history-card">
        <div class="history-table-wrap">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>ルーティンアイテム</th>
                        <th>結果</th>
                        <th>学習開始日</th>
                        <th>学習終了日</th>
                        <th>達成状況</th>
                        <th>完了時進捗率</th>
                        <th>学習履歴</th>
                        <th>自己評価</th>
                        <th>コメント</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($endedRoutineItems as $item)
                        @php
                            $statuses = $allStatuses->get($item->id, collect());
                            [$elapsedDays, $workedDays, $restedDays] = historyDailyStats($item, $statuses);
                            $requiredDays = !empty($item->required_days) ? (int) $item->required_days : null;
                            $progressRate = historyProgressRate($workedDays, $requiredDays);
                            $isAchieved = $requiredDays && $workedDays >= $requiredDays;
                            $resultText = $requiredDays ? ($isAchieved ? '🏆 達成' : '⚠ 未達成') : '—';
                            $resultClass = $requiredDays ? ($isAchieved ? 'success' : 'danger') : 'muted';
                            $donutClass = $progressRate === null ? 'rate-danger' : ($progressRate >= 100 ? 'rate-success' : ($progressRate >= 70 ? 'rate-warning' : 'rate-danger'));
                            [$historyCells, $historyWorkedDays, $historyElapsedDays] = historyCells($item, $statuses, 7);
                        @endphp
                        <tr>
                            <td>
                                <div class="history-item-box">
                                    <span class="history-icon">{{ historyIcon($item->item_name) }}</span>
                                    <div>
                                        <div class="history-title-text">{{ $item->item_name }}</div>
                                        <div class="history-sub">{{ $item->routine?->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center"><span class="history-result {{ $resultClass }}">{{ $resultText }}</span></td>
                            <td class="text-center">{{ $item->start_date ? Carbon::parse($item->start_date)->format('Y/m/d') : '-' }}</td>
                            <td class="text-center">{{ $item->completed_at ? Carbon::parse($item->completed_at)->format('Y/m/d') : '-' }}</td>
                            <td class="text-center">
                                @if($requiredDays)
                                    <span class="history-hover">
                                        {{ $workedDays }} / {{ $requiredDays }}日
                                        <span class="history-tooltip">
                                            <span class="history-tooltip-title">達成状況</span>
                                            取り組み日数：{{ $workedDays }}日<br>
                                            達成必要日数：{{ $requiredDays }}日<br>
                                            達成率：{{ $progressRate }}%
                                        </span>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                @if($progressRate !== null)
                                    <span class="history-hover">
                                        <span class="history-donut {{ $donutClass }}" style="--rate: {{ $progressRate }};">
                                            <span>{{ $progressRate }}%</span>
                                        </span>
                                        <span class="history-tooltip">
                                            <span class="history-tooltip-title">完了時進捗率</span>
                                            取り組み日数：{{ $workedDays }}日<br>
                                            達成必要日数：{{ $requiredDays }}日<br>
                                            進捗率：{{ $progressRate }}%
                                        </span>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="history-hover">
                                    <span>
                                        <span class="history-cells">
                                            @foreach($historyCells as $cell)
                                                <span class="history-cell {{ $cell['class'] }}"></span>
                                            @endforeach
                                        </span>
                                        <div class="history-summary">{{ $historyWorkedDays }}日 / {{ $historyElapsedDays }}日</div>
                                    </span>
                                    <span class="history-tooltip">
                                        <span class="history-tooltip-title">学習履歴</span>
                                        🟩 学習あり<br>
                                        🟥 学習なし<br>
                                        <div style="margin-top:6px;padding-top:6px;border-top:1px solid #e5edf6;">
                                            @foreach($historyCells as $cell)
                                                {{ $cell['date'] }}：{{ $cell['class'] === 'worked' ? '○' : '×' }}<br>
                                            @endforeach
                                        </div>
                                    </span>
                                </span>
                            </td>
                            <td class="text-center">
                                @if($item->self_evaluation_score)
                                    <span class="history-stars">
                                        @for($i = 1; $i <= 5; $i++)
                                            <span class="{{ $i <= $item->self_evaluation_score ? 'star-on' : 'star-off' }}">★</span>
                                        @endfor
                                        <span class="history-score">({{ $item->self_evaluation_score }})</span>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="history-comment">
                                <span class="history-hover">
                                    <span class="history-comment-combined">
                                        <span class="history-comment-line">
                                            <span class="history-comment-label self">本人</span>
                                            <span class="history-comment-text">{{ $item->self_evaluation_comment ?: '未入力' }}</span>
                                        </span>
                                        <span class="history-comment-line">
                                            <span class="history-comment-label teacher">先生</span>
                                            <span class="history-comment-text">{{ $item->teacher_comment ?: '未入力' }}</span>
                                        </span>
                                    </span>
                                    <span class="history-tooltip">
                                        <strong>本人コメント（全文）</strong><br>
                                        {{ $item->self_evaluation_comment ?: '未入力' }}<br>
                                        <div style="height:8px;"></div>
                                        <strong>先生コメント（全文）</strong><br>
                                        {{ $item->teacher_comment ?: '未入力' }}
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">終了したルーティンアイテムはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    let activeHistoryTooltip = null;

    function removeHistoryTooltip() {
        if (activeHistoryTooltip) {
            activeHistoryTooltip.remove();
            activeHistoryTooltip = null;
        }
    }

    function positionHistoryTooltip(trigger) {
        if (!activeHistoryTooltip) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const margin = 10;

        activeHistoryTooltip.style.left = '0px';
        activeHistoryTooltip.style.top = '0px';

        const tooltipRect = activeHistoryTooltip.getBoundingClientRect();

        let left = rect.left;
        let top = rect.bottom + 8;

        if (left + tooltipRect.width + margin > window.innerWidth) {
            left = window.innerWidth - tooltipRect.width - margin;
        }

        if (left < margin) {
            left = margin;
        }

        if (top + tooltipRect.height + margin > window.innerHeight) {
            top = rect.top - tooltipRect.height - 8;
        }

        if (top < margin) {
            top = margin;
        }

        activeHistoryTooltip.style.left = left + 'px';
        activeHistoryTooltip.style.top = top + 'px';
    }

    document.querySelectorAll('.history-hover').forEach(function (trigger) {
        const tooltip = trigger.querySelector('.history-tooltip');

        if (!tooltip) {
            return;
        }

        trigger.addEventListener('mouseenter', function () {
            removeHistoryTooltip();

            activeHistoryTooltip = document.createElement('div');
            activeHistoryTooltip.className = 'history-floating-tooltip';
            activeHistoryTooltip.innerHTML = tooltip.innerHTML;
            document.body.appendChild(activeHistoryTooltip);

            positionHistoryTooltip(trigger);
        });

        trigger.addEventListener('mousemove', function () {
            positionHistoryTooltip(trigger);
        });

        trigger.addEventListener('mouseleave', removeHistoryTooltip);
    });

    window.addEventListener('scroll', removeHistoryTooltip, true);
    window.addEventListener('resize', removeHistoryTooltip);
});
</script>

