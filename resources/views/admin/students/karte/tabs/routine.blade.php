@php
    use Carbon\Carbon;

    $activeRoutines = $activeRoutines ?? collect();
    $endedRoutineItems = $endedRoutineItems ?? collect();
    $endedRoutineItemsTotal = $endedRoutineItemsTotal ?? 0;
    $routinePackages = $routinePackages ?? collect();
    $routinePackageItems = $routinePackageItems ?? collect();
    $todayStatuses = $todayStatuses ?? collect();
    $allStatuses = $allStatuses ?? collect();
    $calendarStatuses = $calendarStatuses ?? collect();
    $today = $today ?? now();

    $completionTypes = \App\Models\RoutineCompletionType::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

    function routineStatusLabel($status) {
        return match ($status) {
            'completed' => ['○ 完了', 'success'],
            'in_progress', 'partial' => ['△ 途中終了', 'warning'],
            default => ['× 未着手', 'danger'],
        };
    }

    function routineConditionText($item) {
        $type = $item->completionType->name ?? '-';
        $unit = $item->completionType->unit ?? '';
        $value = $item->target_value !== null
            ? rtrim(rtrim(number_format($item->target_value, 2), '0'), '.')
            : '-';

        return $type . '<br>' . $value . $unit . '以上';
    }

    function routineActualText($item, $status) {
        if (!$status) {
            return '—';
        }

        if (!empty($status->study_seconds)) {
            $minutes = floor($status->study_seconds / 60);
            $seconds = str_pad($status->study_seconds % 60, 2, '0', STR_PAD_LEFT);
            return $minutes . '分' . $seconds . '秒';
        }

        return '—';
    }

   function routineAchievement($item, $statuses)
    {
        $start = $item && $item->start_date
            ? Carbon::parse($item->start_date)->startOfDay()
            : now()->startOfDay();

        $end = now()->startOfDay();

        $elapsed = (int) $start->diffInDays($end) + 1;
        $elapsed = max(1, $elapsed);

        $achieved = $statuses
            ? (int) $statuses->where('status', 'completed')->count()
            : 0;

        $achieved = min($achieved, $elapsed);

        return [$achieved, $elapsed];
    }


    function routineDailyStats($item, $statuses) {
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
        $statusRows = $statuses ? $statuses : collect();

        $workedDays = $statusRows
            ->filter(function ($status) use ($start, $end) {
                $targetDate = Carbon::parse($status->target_date)->startOfDay();
                return $targetDate->betweenIncluded($start, $end)
                    && in_array($status->status, ['completed', 'partial', 'in_progress'], true);
            })
            ->pluck('target_date')
            ->unique()
            ->count();

        $achievedDays = $statusRows
            ->filter(function ($status) use ($start, $end) {
                $targetDate = Carbon::parse($status->target_date)->startOfDay();
                return $targetDate->betweenIncluded($start, $end)
                    && $status->status === 'completed';
            })
            ->pluck('target_date')
            ->unique()
            ->count();

        $workedDays = min($workedDays, $elapsedDays);
        $achievedDays = min($achievedDays, $elapsedDays);
        $restedDays = max(0, $elapsedDays - $workedDays);

        return [$elapsedDays, $workedDays, $restedDays, $achievedDays];
    }

    function routineMinimumDays($item) {
        foreach (['minimum_days', 'min_days', 'required_days', 'target_days', 'minimum_achievement_days'] as $key) {
            if (isset($item->{$key}) && $item->{$key} !== null && $item->{$key} !== '') {
                return (int) $item->{$key};
            }
        }

        if (isset($item->routinePackageItem)) {
            foreach (['minimum_days', 'min_days', 'required_days', 'target_days', 'minimum_achievement_days'] as $key) {
                if (isset($item->routinePackageItem->{$key}) && $item->routinePackageItem->{$key} !== null && $item->routinePackageItem->{$key} !== '') {
                    return (int) $item->routinePackageItem->{$key};
                }
            }
        }

        return null;
    }

    function routineProgressRate($workedDays, $minimumDays, $fallbackRate = 0) {
        if ($minimumDays && $minimumDays > 0) {
            return min(100, (int) round(($workedDays / $minimumDays) * 100));
        }

        return 0;
    }

    function routinePaceData($elapsedDays, $workedDays, $minimumDays) {
        if (!$minimumDays || $minimumDays <= 0) {
            return [
                'available' => false,
                'planned' => null,
                'actual' => null,
                'diff' => null,
                'symbol' => '—',
                'color' => 'muted',
                'label' => '—',
            ];
        }

        $planned = min(100, (int) round(($elapsedDays / $minimumDays) * 100));
        $actual = min(100, (int) round(($workedDays / $minimumDays) * 100));
        $diff = $actual - $planned;

        if ($diff > 0) {
            $symbol = '↑';
            $color = 'success';
            $label = '+' . $diff . '%';
        } elseif ($diff < 0) {
            $symbol = '↓';
            $color = 'danger';
            $label = $diff . '%';
        } else {
            $symbol = '→';
            $color = 'warning';
            $label = '0%';
        }

        return [
            'available' => true,
            'planned' => $planned,
            'actual' => $actual,
            'diff' => $diff,
            'symbol' => $symbol,
            'color' => $color,
            'label' => $label,
        ];
    }

    function routineHistoryCells($item, $statuses, $displayDays = 7) {
        $start = $item && $item->start_date
            ? Carbon::parse($item->start_date)->startOfDay()
            : now()->startOfDay();

        $today = now()->startOfDay();
        if ($today->lt($start)) {
            $today = $start->copy();
        }

        $elapsed = max(1, (int) $start->diffInDays($today) + 1);
        $daysToShow = min($displayDays, $elapsed);
        $first = $today->copy()->subDays($daysToShow - 1);
        if ($first->lt($start)) {
            $first = $start->copy();
        }

        $statusRows = $statuses ? $statuses->keyBy(fn($s) => Carbon::parse($s->target_date)->toDateString()) : collect();
        $cells = [];
        $worked = 0;

        for ($date = $first->copy(); $date->lte($today); $date->addDay()) {
            $key = $date->toDateString();
            $row = $statusRows->get($key);
            $status = $row->status ?? 'not_started';
            $isWorked = in_array($status, ['completed', 'partial', 'in_progress'], true);

            if ($isWorked) {
                $worked++;
            }

            $cells[] = [
                'date' => routineJapaneseDate($key),
                'status' => $status,
                'mark' => $isWorked ? '●' : '×',
                'class' => $isWorked ? 'worked' : 'missed',
            ];
        }

        return [$cells, $worked, $elapsed];
    }

    function routineEndedHistoryCells($item, $statuses, $displayDays = 7) {
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
                'date' => routineJapaneseDate($key),
                'status' => $status,
                'mark' => $isWorked ? '●' : '×',
                'class' => $isWorked ? 'worked' : 'missed',
            ];
        }

        return [$cells, $worked, $elapsed];
    }

    function routineJapaneseDate($date) {
        $carbon = Carbon::parse($date);
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        return $carbon->format('Y/m/d') . '(' . $weekdays[$carbon->dayOfWeek] . ')';
    }

    function routineResultLabel($rate) {
        if ($rate >= 90) return ['達成', 'success'];
        if ($rate >= 50) return ['一部達成', 'warning'];
        return ['未達成', 'danger'];
    }

    function routineConsecutiveMissedDays($historyCells) {
        $count = 0;
        $reversed = array_reverse($historyCells ?? []);

        foreach ($reversed as $cell) {
            if (($cell['class'] ?? '') === 'missed') {
                $count++;
                continue;
            }

            break;
        }

        return $count;
    }

    function routineFollowStatus($minimumDays, $paceData, $todayStatus, $historyCells) {
        if (!$minimumDays || $minimumDays <= 0 || !($paceData['available'] ?? false)) {
            return [
                '—',
                'none',
                '達成必要日数が未設定のため、状態判定の対象外です。',
            ];
        }

        $todayStatusValue = $todayStatus->status ?? 'not_started';
        $diff = (int) ($paceData['diff'] ?? 0);
        $missedDays = routineConsecutiveMissedDays($historyCells);
        $diffLabel = ($diff > 0 ? '+' : '') . $diff . '%';

        if ($missedDays >= 3) {
            return [
                '🔴 フォロー急務',
                'danger',
                '直近' . $missedDays . '日連続で未実施のため、至急フォローが必要です。',
            ];
        }

        if ($diff <= -40) {
            return [
                '🔴 フォロー急務',
                'danger',
                '学習ペース差分が' . $diffLabel . 'のため、至急フォローが必要です。',
            ];
        }

        if ($missedDays >= 2) {
            return [
                '🟠 フォロー必要',
                'warning',
                '直近' . $missedDays . '日連続で未実施のため、先生の声かけが必要です。',
            ];
        }

        if ($diff <= -20 && $diff >= -39) {
            return [
                '🟠 フォロー必要',
                'warning',
                '学習ペース差分が' . $diffLabel . 'のため、フォローが必要です。',
            ];
        }

        if ($todayStatusValue === 'not_started' && $diff <= -1 && $diff >= -19) {
            return [
                '🟡 要確認',
                'caution',
                '今日が未着手で、学習ペース差分が' . $diffLabel . 'のため、様子確認が必要です。',
            ];
        }

        if ($todayStatusValue === 'completed') {
            return [
                '🟢 順調',
                'success',
                '今日のステータスが完了のため、順調です。',
            ];
        }

        if ($diff >= 0) {
            return [
                '🟢 順調',
                'success',
                '学習ペース差分が' . $diffLabel . 'のため、予定以上の進み具合です。',
            ];
        }

        return [
            '🟡 要確認',
            'caution',
            '学習ペース差分が' . $diffLabel . 'のため、様子確認が必要です。',
        ];
    }

    function routineIcon($name, $type = 'item') {
        if (str_contains($name, '脳')) return '🧠';
        if (str_contains($name, '将棋')) return '将';
        if (str_contains($name, '英検')) return '📖';
        if (str_contains($name, '数字')) return '数';
        if (str_contains($name, '不足')) return '🔍';
        if (str_contains($name, 'シルエット')) return 'シ';
        if (str_contains($name, '九九')) return '九';
        if (str_contains($name, 'リスニング')) return '🎧';
        return $type === 'routine' ? 'R' : '●';
    }

    function calendarMark($status) {
        return match ($status) {
            'completed' => ['○', 'success'],
            'partial', 'in_progress' => ['△', 'warning'],
            default => ['×', 'danger'],
        };
    }
@endphp

<style>

    #editModal1 .routine-modal-body,
    .routine-modal[id^="editModal"] .routine-modal-body {
        padding-top: 22px;
    }

    .routine-modal[id^="editModal"] .routine-modal-dialog {
        margin-top: 64px;
    }

    .routine-modal[id^="editModal"] .routine-modal-content {
    transform: translateY(0);
    }

    .routine-wrap {
        font-size: 13px;
        color: #0f172a;
    }

    .routine-success {
        background: #ecfdf5;
        border: 1px solid #bbf7d0;
        color: #15803d;
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 12px;
        font-weight: 700;
    }

    .routine-card {
        background: #ffffff;
        border: 1px solid #d8e3f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 14px;
    }

    .routine-card-header {
        padding: 12px 14px;
        font-weight: 800;
        color: #0f376d;
        border-bottom: 1px solid #d8e3f0;
        background: #f8fbff;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .routine-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .routine-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }

    .routine-table th {
        background: #f4f7fb;
        color: #111827;
        font-weight: 800;
        border: 1px solid #dfe7f1;
        padding: 10px 8px;
        text-align: center;
        white-space: nowrap;
        font-size: 12px;
    }

    .routine-table td {
        border: 1px solid #dfe7f1;
        padding: 10px 8px;
        vertical-align: middle;
        background: #fff;
    }

    .routine-main-table {
        min-width: 1420px;
    }

    .routine-package-box,
    .routine-item-box {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .routine-package-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #2563eb;
        font-weight: 900;
        font-size: 20px;
        flex-shrink: 0;
    }

    .routine-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 900;
        font-size: 15px;
        flex-shrink: 0;
    }

    .routine-icon-blue { background: #2563eb; }
    .routine-icon-orange { background: #f97316; }
    .routine-icon-green { background: #16a34a; }
    .routine-icon-purple { background: #7c3aed; }

    .routine-title {
        font-weight: 800;
        line-height: 1.3;
        color: #0f172a;
    }

    .routine-sub {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
        line-height: 1.4;
    }

    .routine-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #b8cdf5;
        color: #2563eb;
        background: #ffffff;
        border-radius: 6px;
        padding: 5px 8px;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        margin: 2px;
        white-space: nowrap;
        cursor: pointer;
        line-height: 1.2;
    }

    .routine-btn:hover {
        background: #eff6ff;
    }

    .routine-btn-green {
        border-color: #bde7c9;
        color: #16a34a;
    }

    .routine-btn-green:hover {
        background: #f0fdf4;
    }

    .routine-btn-purple {
        border-color: #d7c7ff;
        color: #7c3aed;
    }

    .routine-btn-purple:hover {
        background: #f5f3ff;
    }

    .routine-btn-red {
        border-color: #fecaca;
        color: #dc2626;
    }

    .routine-btn-red:hover {
        background: #fef2f2;
    }

    .routine-add-btn {
        background: #16a34a;
        color: #fff;
        border: 0;
        border-radius: 6px;
        padding: 5px 9px;
        font-size: 12px;
        text-decoration: none;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
    }

    .routine-badge {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 7px;
        font-weight: 800;
        font-size: 12px;
        white-space: nowrap;
    }

    .badge-success {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-warning {
        background: #ffedd5;
        color: #ea580c;
    }

    .badge-danger {
        background: #fee2e2;
        color: #dc2626;
    }

    .routine-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .routine-search-row {
        display: grid;
        grid-template-columns: 1.1fr 1.3fr 0.9fr 0.9fr 0.9fr 70px 70px;
        gap: 8px;
        padding: 12px;
        align-items: center;
    }

    .routine-search-row-small {
        display: grid;
        grid-template-columns: 1.1fr 1.3fr 0.9fr 0.9fr 0.9fr 70px 70px;
        gap: 8px;
        padding: 12px;
        align-items: center;
    }

    .routine-input,
    .routine-select {
        height: 34px;
        border: 1px solid #dbe5f2;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 12px;
        width: 100%;
        background: #fff;
    }

    .routine-primary-btn {
        height: 34px;
        border: 0;
        background: #2563eb;
        color: #fff;
        border-radius: 6px;
        font-weight: 800;
        cursor: pointer;
    }

    .routine-clear-btn {
        height: 34px;
        border: 1px solid #b8cdf5;
        background: #fff;
        color: #2563eb;
        border-radius: 6px;
        font-weight: 800;
        text-align: center;
        line-height: 32px;
        text-decoration: none;
    }

    .routine-mini-table {
        min-width: 760px;
    }




    .routine-item-plus-add {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        margin-right: 4px;
        border-radius: 999px;
        background: #16a34a;
        color: #fff;
        font-size: 15px;
        font-weight: 900;
        line-height: 22px;
        text-decoration: none;
        vertical-align: middle;
    }

    .routine-item-plus-add:hover {
        background: #15803d;
        color: #fff;
        text-decoration: none;
    }

    .routine-inline-add-form {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin: 0;
        white-space: nowrap;
    }

    .routine-plus-add-btn {
        width: 22px;
        height: 22px;
        border: 0;
        border-radius: 999px;
        background: #16a34a;
        color: #fff;
        font-size: 15px;
        font-weight: 900;
        line-height: 22px;
        cursor: pointer;
        padding: 0;
    }

    .routine-plus-add-btn:hover {
        background: #15803d;
    }

    .routine-package-id-text {
        font-size: 12px;
        font-weight: 800;
        color: #334155;
    }

    .routine-package-items-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
    }

    .routine-package-item-tag {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 999px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.35;
        white-space: nowrap;
    }

    .routine-package-items-preview {
        display: flex;
        flex-direction: column;
        gap: 3px;
        align-items: flex-start;
    }

    .routine-package-item-line {
        display: flex;
        gap: 6px;
        align-items: baseline;
        font-size: 13px;
        line-height: 1.45;
        max-width: 100%;
    }

    .routine-package-item-code {
        color: #2563eb;
        font-weight: 800;
        white-space: nowrap;
    }

    .routine-grade-chip {
        display: inline-block;
        padding: 2px 6px;
        margin: 1px 2px;
        border-radius: 999px;
        background: #eef6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.2;
    }


    .routine-package-name-wrap {
        display: inline;
        white-space: normal;
        word-break: break-word;
    }

    .routine-package-name-text {
        display: inline;
        white-space: normal;
        word-break: break-word;
    }

    .routine-description-icon-inline {
        margin-left: 6px;
        vertical-align: middle;
    }

    .routine-description-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 24px;
        border-radius: 6px;
        background: #f8fafc;
        border: 1px solid #dbe5f2;
        cursor: help;
    }

    .routine-package-item-name {
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 230px;
    }

    .muted-text {
        color: #64748b;
        font-size: 12px;
    }

    .text-center {
        text-align: center;
    }

    .routine-more {
        padding: 12px;
        text-align: center;
        border-top: 1px solid #dfe7f1;
    }


    .modal-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 800;
        margin-bottom: 5px;
    }

    .modal-value {
        padding: 9px 11px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        min-height: 38px;
        line-height: 1.6;
    }

    .routine-form-group {
        margin-bottom: 12px;
    }

    .routine-form-label {
        display: block;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 5px;
        color: #334155;
    }

    .routine-form-control,
    .routine-form-select {
        width: 100%;
        height: 38px;
        border: 1px solid #d8e3f0;
        border-radius: 7px;
        padding: 7px 9px;
        background: #fff;
    }

    textarea.routine-form-control {
        height: 92px;
        resize: vertical;
    }

    .routine-modal-button {
        border: 0;
        border-radius: 8px;
        padding: 8px 18px;
        font-weight: 900;
        cursor: pointer;
    }

    .routine-modal-button-primary {
        background: #2563eb;
        color: #fff;
    }

    .routine-modal-button-light {
        background: #e2e8f0;
        color: #334155;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
    }

    .calendar-cell {
        border: 1px solid #dfe7f1;
        border-radius: 9px;
        padding: 8px 4px;
        text-align: center;
        background: #ffffff;
        min-height: 56px;
    }

    .calendar-date {
        font-size: 11px;
        color: #64748b;
    }

    .calendar-mark {
        font-size: 20px;
        font-weight: 900;
        margin-top: 2px;
    }

    .calendar-success {
        color: #15803d;
    }

    .calendar-warning {
        color: #ea580c;
    }

    .calendar-danger {
        color: #dc2626;
    }

    @media (max-width: 1200px) {
        .routine-grid {
            grid-template-columns: 1fr;
        }
    }

    .routine-form-group {
        margin-bottom: 16px;
    }

    .routine-form-label {
        margin-bottom: 6px;
    }

    .routine-form-control,
    .routine-form-select {
        height: 42px;
        font-size: 14px;
    }

    textarea.routine-form-control {
        height: 110px;
        line-height: 1.6;
    }

    .routine-modal-button {
        min-width: 82px;
    }

    .routine-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.48);
        overflow-y: auto;
        padding: 64px 16px;
    }

    .routine-modal.is-open {
        display: block;
    }

    .routine-modal-dialog,
    .routine-modal-dialog-sm {
        width: min(720px, calc(100vw - 40px));
        margin: 0 auto;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
    }

    .routine-modal-content {
        width: 100%;
        background: #ffffff;
        border: 1px solid #d8e3f0;
        border-radius: 14px;
        box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28);
        overflow: hidden;
        margin: 0;
        padding: 0;
    }

    .routine-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 22px;
        background: #f8fbff;
        border-bottom: 1px solid #e5edf6;
    }

    .routine-modal-title {
        margin: 0;
        font-size: 16px;
        font-weight: 900;
        color: #0f376d;
    }

    .routine-modal-close {
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 50%;
        background: #e5edf6;
        color: #334155;
        font-size: 20px;
        font-weight: 800;
        cursor: pointer;
    }

    .routine-modal-body {
        padding: 22px 24px;
        background: #ffffff;
    }

    .routine-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 24px;
        background: #f8fafc;
        border-top: 1px solid #e5edf6;
    }

    .routine-form-group {
        margin-bottom: 16px;
    }

    .routine-form-label {
        display: block;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 6px;
        color: #334155;
    }

    .routine-form-control,
    .routine-form-select {
        width: 100%;
        height: 42px;
        border: 1px solid #d8e3f0;
        border-radius: 7px;
        padding: 7px 10px;
        font-size: 14px;
        background: #ffffff;
    }

    .routine-btn-orange {
        border-color: #fdba74;
        color: #ea580c;
    }

    .routine-btn-orange:hover {
        background: #fff7ed;
    }

    textarea.routine-form-control {
        height: 110px;
        line-height: 1.6;
    }


    .routine-main-table {
        min-width: 1780px;
    }

    .routine-group-row td {
        background: #eef6ff;
        border: 1px solid #cfe2ff;
        padding: 12px 14px;
    }

    .routine-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        color: #0f376d;
        font-weight: 900;
    }

    .routine-group-name {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
    }

    .routine-item-cell {
        min-width: 360px;
        max-width: 420px;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .routine-item-cell .routine-title {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .routine-item-actions {
        margin-top: 6px;
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .routine-progress-wrap {
        min-width: 120px;
    }

    .routine-progress-bar {
        width: 100%;
        height: 9px;
        background: #e5e7eb;
        border-radius: 999px;
        overflow: hidden;
        margin-bottom: 4px;
    }

    .routine-progress-fill {
        height: 100%;
        background: #2563eb;
        border-radius: 999px;
    }

    .routine-progress-text {
        font-size: 11px;
        font-weight: 800;
        color: #334155;
    }

    .routine-btn-cyan {
        border-color: #a5f3fc;
        color: #0891b2;
    }

    .routine-btn-cyan:hover {
        background: #ecfeff;
    }

    .routine-btn-gray {
        border-color: #cbd5e1;
        color: #475569;
    }

    .routine-btn-gray:hover {
        background: #f8fafc;
    }

    .routine-btn-disabled,
    .routine-btn:disabled {
        border-color: #e5e7eb !important;
        background: #f3f4f6 !important;
        color: #9ca3af !important;
        cursor: default !important;
        pointer-events: none;
    }

    .routine-action-stack,
    .routine-material-stack {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px;
    }

    .calendar-cell-wide {
        min-height: 64px;
    }

    .calendar-date-wide {
        font-size: 11px;
        color: #64748b;
        white-space: nowrap;
    }


    /* No1/No2/No6 修正版：ルーティン単位カード、列幅最適化、進捗グラフ拡張 */
    .routine-routine-list {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background: #f8fbff;
    }

    .routine-routine-card {
        background: #ffffff;
        border: 1px solid #cfe2ff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
    }

    .routine-routine-header {
        padding: 12px 14px;
        background: linear-gradient(90deg, #eef6ff, #f8fbff);
        border-bottom: 1px solid #d8e3f0;
    }

    .routine-routine-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .routine-routine-name {
        font-size: 15px;
        font-weight: 900;
        color: #0f376d;
        line-height: 1.3;
    }

    .routine-routine-sub {
        margin-top: 3px;
        font-size: 12px;
        color: #64748b;
        font-weight: 700;
    }

    .routine-empty-card {
        padding: 18px;
        text-align: center;
        background: #ffffff;
        border: 1px solid #d8e3f0;
        border-radius: 10px;
        color: #64748b;
        font-weight: 700;
    }

    .routine-card-table {
        min-width: 1990px;
        table-layout: fixed;
    }

    .routine-card-table .routine-item-cell {
        width: 500px;
        min-width: 500px;
        max-width: 500px;
        white-space: normal;
        overflow-wrap: break-word;
        word-break: keep-all;
    }

    .routine-card-table .routine-item-cell .routine-item-box {
        align-items: flex-start;
    }

    .routine-card-table .routine-item-cell .routine-title {
        white-space: normal;
        overflow-wrap: break-word;
        word-break: keep-all;
        line-height: 1.45;
    }

    .routine-progress-wrap {
        min-width: 210px;
        max-width: 230px;
        margin: 0 auto;
    }

    .routine-progress-bar {
        height: 12px;
        margin-bottom: 6px;
    }

    .routine-progress-text {
        font-size: 12px;
        font-weight: 900;
    }

    .routine-card-table th:nth-child(2),
    .routine-card-table td:nth-child(2),
    .routine-card-table th:nth-child(7),
    .routine-card-table td:nth-child(7),
    .routine-card-table th:nth-child(8),
    .routine-card-table td:nth-child(8),
    .routine-card-table th:nth-child(9),
    .routine-card-table td:nth-child(9),
    .routine-card-table th:nth-child(12),
    .routine-card-table td:nth-child(12) {
        padding-left: 4px;
        padding-right: 4px;
        white-space: nowrap;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        padding-left: 12px;
        padding-right: 12px;
    }

    .routine-group-row {
        display: none;
    }


    /* Rev4: 表示崩れ修正、横幅再調整、進捗グラフ改善 */
    html, body {
        overflow-x: hidden;
    }

    .routine-wrap,
    .routine-card,
    .routine-routine-list,
    .routine-routine-card,
    .routine-table-wrap {
        max-width: 100%;
        box-sizing: border-box;
    }

    .routine-routine-card {
        overflow: hidden;
    }

    .routine-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
    }

    .routine-card-table {
        min-width: 1720px;
        table-layout: fixed;
    }

    .routine-card-table .routine-item-cell {
        width: 520px;
        min-width: 520px;
        max-width: 520px;
    }

    .routine-card-table .routine-item-cell .routine-title {
        word-break: keep-all;
        overflow-wrap: anywhere;
        line-height: 1.45;
    }

    .routine-card-table th:nth-child(5),
    .routine-card-table td:nth-child(5) {
        width: 92px;
        min-width: 92px;
        max-width: 92px;
    }

    .routine-card-table th:nth-child(6),
    .routine-card-table td:nth-child(6),
    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 108px;
        min-width: 108px;
        max-width: 108px;
    }

    .routine-card-table th:nth-child(14),
    .routine-card-table td:nth-child(14) {
        width: 130px;
        min-width: 130px;
        max-width: 130px;
    }

    .routine-progress-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        min-width: 210px;
        max-width: none;
        margin: 0 auto;
    }

    .routine-progress-bar {
        flex: 1 1 auto;
        width: auto;
        min-width: 145px;
        height: 10px;
        margin: 0;
    }

    .routine-progress-fill {
        background: #16a34a;
        min-width: 0;
    }

    .routine-progress-text {
        flex: 0 0 38px;
        text-align: right;
        font-size: 11px;
        font-weight: 900;
    }

    .routine-action-stack {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-areas:
            "finish finish"
            "edit delete";
        justify-items: center;
        align-items: center;
        gap: 5px;
    }

    .routine-action-finish {
        grid-area: finish;
    }

    .routine-action-edit {
        grid-area: edit;
    }

    .routine-action-delete {
        grid-area: delete;
    }

    .routine-action-stack form {
        margin: 0;
    }

    .routine-action-stack .routine-btn {
        margin: 0;
        padding: 5px 7px;
    }


    /* Rev5: 指定修正：ドーナツ進捗、列幅再調整、縦ボタン、空パック削除、サイドメニュー保護 */
    .routine-wrap {
        max-width: 100%;
        overflow-x: clip;
    }

    .routine-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
        max-width: 100%;
    }

    .routine-card-table {
        min-width: 1815px;
        table-layout: fixed;
    }

    .routine-card-table .routine-item-cell {
        width: 780px !important;
        min-width: 780px !important;
        max-width: 780px !important;
    }

    .routine-card-table th,
    .routine-card-table td {
        box-sizing: border-box;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
        padding-left: 4px;
        padding-right: 4px;
    }

    .routine-card-table th:nth-child(5),
    .routine-card-table td:nth-child(5) {
        width: 82px !important;
        min-width: 82px !important;
        max-width: 82px !important;
        padding-left: 4px;
        padding-right: 4px;
    }

    .routine-card-table th:nth-child(6),
    .routine-card-table td:nth-child(6),
    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 88px !important;
        min-width: 88px !important;
        max-width: 88px !important;
        padding-left: 4px;
        padding-right: 4px;
    }

    .routine-card-table th:nth-child(7),
    .routine-card-table td:nth-child(7),
    .routine-card-table th:nth-child(9),
    .routine-card-table td:nth-child(9),
    .routine-card-table th:nth-child(10),
    .routine-card-table td:nth-child(10),
    .routine-card-table th:nth-child(12),
    .routine-card-table td:nth-child(12) {
        width: 60px !important;
        min-width: 60px !important;
        max-width: 60px !important;
        padding-left: 3px;
        padding-right: 3px;
        white-space: nowrap;
    }

    .routine-card-table th:nth-child(8),
    .routine-card-table td:nth-child(8) {
        width: 72px !important;
        min-width: 72px !important;
        max-width: 72px !important;
        padding-left: 3px;
        padding-right: 3px;
        white-space: nowrap;
    }

    .routine-card-table th:nth-child(13),
    .routine-card-table td:nth-child(13) {
        width: 64px !important;
        min-width: 64px !important;
        max-width: 64px !important;
        padding-left: 4px;
        padding-right: 4px;
    }

    .routine-card-table th:nth-child(14),
    .routine-card-table td:nth-child(14) {
        width: 76px !important;
        min-width: 76px !important;
        max-width: 76px !important;
        padding-left: 4px;
        padding-right: 4px;
    }

    .routine-card-table th:nth-child(15),
    .routine-card-table td:nth-child(15) {
        width: 56px !important;
        min-width: 56px !important;
        max-width: 56px !important;
        padding-left: 4px;
        padding-right: 4px;
    }

    .routine-donut {
        --rate: 0;
        width: 46px;
        height: 46px;
        margin: 0 auto;
        border-radius: 50%;
        background: conic-gradient(#16a34a calc(var(--rate) * 1%), #e5e7eb 0);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        flex: 0 0 auto;
    }

    .routine-donut::before {
        content: "";
        position: absolute;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #ffffff;
    }

    .routine-donut span {
        position: relative;
        z-index: 1;
        font-size: 10px;
        font-weight: 900;
        color: #0f172a;
        line-height: 1;
    }

    .routine-material-stack {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }

    .routine-material-stack .routine-btn {
        width: 42px;
        min-width: 42px;
        padding: 4px 3px;
        margin: 0;
        font-size: 10px;
    }

    .routine-action-stack {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }

    .routine-action-stack .routine-btn,
    .routine-action-stack form {
        width: 58px;
        min-width: 58px;
        margin: 0;
    }

    .routine-action-stack form .routine-btn {
        width: 58px;
        min-width: 58px;
        padding: 4px 3px;
    }

    .routine-action-stack .routine-btn {
        padding: 4px 3px;
        font-size: 10px;
    }

    .routine-action-finish {
        order: 1;
    }

    .routine-action-edit {
        order: 2;
    }

    .routine-action-delete {
        order: 3;
    }

    .routine-calendar-icon-btn {
        width: 34px;
        min-width: 34px;
        height: 30px;
        padding: 0;
        font-size: 14px;
        margin: 0;
    }

    .routine-empty-actions {
        margin-top: 8px;
    }

    .routine-empty-delete-btn {
        border-color: #fecaca;
        color: #dc2626;
        background: #ffffff;
    }

    .routine-empty-delete-btn:hover {
        background: #fef2f2;
    }

    /* 共通左メニューの潰れ対策：ルーティン表の横幅に引っ張られて縮まないようにする */
    body > aside,
    aside[class*="sidebar"],
    .sidebar,
    .admin-sidebar,
    .side-menu,
    .side-nav,
    nav[class*="sidebar"] {
        flex-shrink: 0 !important;
        word-break: keep-all;
        overflow-wrap: normal;
    }

    body > aside a,
    aside[class*="sidebar"] a,
    .sidebar a,
    .admin-sidebar a,
    .side-menu a,
    .side-nav a,
    nav[class*="sidebar"] a {
        word-break: keep-all;
        overflow-wrap: normal;
    }


    /* Rev6: 横スクロール、新規空ルーティン作成、空パック表示維持 */
    .routine-wrap {
        max-width: 100%;
        overflow-x: visible !important;
    }

    .routine-card {
        overflow: hidden;
    }

    .routine-routine-card {
        overflow: hidden;
    }

    .routine-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: scroll !important;
        overflow-y: visible;
        padding-bottom: 10px;
        scrollbar-width: auto;
    }

    .routine-table-wrap::-webkit-scrollbar {
        height: 12px;
    }

    .routine-table-wrap::-webkit-scrollbar-track {
        background: #eef2f7;
        border-radius: 999px;
    }

    .routine-table-wrap::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 999px;
    }

    .routine-card-table {
        min-width: 1900px !important;
        width: 1900px !important;
        table-layout: fixed;
    }

    .routine-card-table .routine-item-cell {
        width: 560px !important;
        min-width: 560px !important;
        max-width: 560px !important;
    }

    .routine-card-table th:nth-child(13),
    .routine-card-table td:nth-child(13) {
        width: 58px !important;
        min-width: 58px !important;
        max-width: 58px !important;
    }

    .routine-card-table th:nth-child(14),
    .routine-card-table td:nth-child(14) {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
    }

    .routine-card-table th:nth-child(15),
    .routine-card-table td:nth-child(15) {
        width: 52px !important;
        min-width: 52px !important;
        max-width: 52px !important;
    }

    .routine-card-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .routine-new-btn {
        border-color: #93c5fd;
        background: #2563eb;
        color: #ffffff;
        padding: 6px 12px;
    }

    .routine-new-btn:hover {
        background: #1d4ed8;
    }

    .routine-empty-actions {
        display: inline-flex;
        justify-content: center;
        align-items: center;
    }


    /* Rev7: 横スクロール・アクション2x2・補助教材/カレンダー幅調整 */
    .routine-routine-card {
        max-width: 100%;
    }

    .routine-table-wrap {
        display: block;
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 12px;
    }

    .routine-table-wrap::-webkit-scrollbar {
        height: 13px;
    }

    .routine-table-wrap::-webkit-scrollbar-track {
        background: #eef2f7;
        border-radius: 999px;
    }

    .routine-table-wrap::-webkit-scrollbar-thumb {
        background: #64748b;
        border-radius: 999px;
    }

    .routine-card-table {
        min-width: 1760px !important;
        width: 1760px !important;
        table-layout: fixed !important;
    }

    .routine-card-table th:nth-child(1),
    .routine-card-table td:nth-child(1) {
        width: 650px !important;
        min-width: 650px !important;
        max-width: 650px !important;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        width: 90px !important;
        min-width: 90px !important;
        max-width: 90px !important;
    }

    .routine-card-table th:nth-child(13),
    .routine-card-table td:nth-child(13) {
        width: 58px !important;
        min-width: 58px !important;
        max-width: 58px !important;
        padding-left: 3px !important;
        padding-right: 3px !important;
    }

    .routine-card-table th:nth-child(14),
    .routine-card-table td:nth-child(14) {
        width: 116px !important;
        min-width: 116px !important;
        max-width: 116px !important;
        padding-left: 4px !important;
        padding-right: 4px !important;
    }

    .routine-card-table th:nth-child(15),
    .routine-card-table td:nth-child(15) {
        width: 54px !important;
        min-width: 54px !important;
        max-width: 54px !important;
        padding-left: 3px !important;
        padding-right: 3px !important;
    }

    .routine-material-stack {
        display: flex !important;
        flex-direction: column !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 4px !important;
    }

    .routine-material-stack .routine-btn {
        width: 42px;
        min-width: 42px;
        padding: 4px 5px;
        font-size: 11px;
        line-height: 1.1;
    }

    .routine-action-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px;
        align-items: center;
        justify-items: center;
        width: 104px;
        margin: 0 auto;
    }

    .routine-action-grid .routine-btn {
        width: 48px;
        min-width: 48px;
        padding: 4px 4px;
        font-size: 11px;
        line-height: 1.15;
        white-space: nowrap;
    }

    .routine-action-grid .routine-action-finish {
        grid-column: 1;
        grid-row: 1;
        width: 50px;
        color: #ea580c;
    }

    .routine-action-grid .routine-action-move {
        grid-column: 2;
        grid-row: 1;
        width: 50px;
    }

    .routine-action-grid .routine-action-edit {
        grid-column: 1;
        grid-row: 2;
    }

    .routine-action-grid .routine-action-delete {
        grid-column: 2;
        grid-row: 2;
        display: block !important;
        margin: 0;
    }

    .routine-action-grid .routine-action-delete .routine-btn {
        width: 48px;
        min-width: 48px;
    }

    .routine-calendar-icon-btn {
        width: 32px !important;
        min-width: 32px !important;
        height: 30px !important;
        padding: 0 !important;
        font-size: 14px !important;
    }


    /* Rev8: 横スクロール最終調整、列幅調整、アクション配置、状態幅、左メニュー保護 */
    .routine-card-header {
        justify-content: flex-start !important;
    }

    .routine-card-title-with-action {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .routine-new-btn {
        padding: 5px 10px !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
    }

    .routine-wrap {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: visible !important;
    }

    .routine-card {
        width: 100% !important;
        max-width: 100% !important;
        overflow: visible !important;
    }

    .routine-routine-list,
    .routine-routine-card {
        width: 100% !important;
        max-width: 100% !important;
        overflow: visible !important;
    }

    .routine-table-wrap {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
        padding-bottom: 12px !important;
        -webkit-overflow-scrolling: touch;
    }

    .routine-table-wrap::-webkit-scrollbar {
        height: 13px;
    }

    .routine-table-wrap::-webkit-scrollbar-track {
        background: #eef2f7;
        border-radius: 999px;
    }

    .routine-table-wrap::-webkit-scrollbar-thumb {
        background: #64748b;
        border-radius: 999px;
    }

    .routine-card-table {
        min-width: 1460px !important;
        width: 1460px !important;
        table-layout: fixed !important;
    }

    .routine-card-table th,
    .routine-card-table td {
        box-sizing: border-box !important;
    }

    .routine-card-table th:nth-child(1),
    .routine-card-table td:nth-child(1) {
        width: 330px !important;
        min-width: 330px !important;
        max-width: 330px !important;
    }

    .routine-card-table .routine-item-cell {
        width: 330px !important;
        min-width: 330px !important;
        max-width: 330px !important;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        width: 86px !important;
        min-width: 86px !important;
        max-width: 86px !important;
    }

    .routine-card-table th:nth-child(5),
    .routine-card-table td:nth-child(5) {
        width: 82px !important;
        min-width: 82px !important;
        max-width: 82px !important;
    }

    .routine-card-table th:nth-child(6),
    .routine-card-table td:nth-child(6) {
        width: 96px !important;
        min-width: 96px !important;
        max-width: 96px !important;
    }

    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 112px !important;
        min-width: 112px !important;
        max-width: 112px !important;
    }

    .routine-card-table th:nth-child(14),
    .routine-card-table td:nth-child(14) {
        width: 104px !important;
        min-width: 104px !important;
        max-width: 104px !important;
        padding-left: 4px !important;
        padding-right: 4px !important;
    }

    .routine-card-table th:nth-child(15),
    .routine-card-table td:nth-child(15) {
        width: 54px !important;
        min-width: 54px !important;
        max-width: 54px !important;
        padding-left: 3px !important;
        padding-right: 3px !important;
    }

    .routine-action-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        grid-template-areas:
            "finish move"
            "edit delete" !important;
        gap: 4px !important;
        align-items: center !important;
        justify-items: center !important;
        width: 96px !important;
        margin: 0 auto !important;
    }

    .routine-action-grid .routine-action-finish {
        grid-area: finish !important;
        width: 44px !important;
        min-width: 44px !important;
    }

    .routine-action-grid .routine-action-move {
        grid-area: move !important;
        width: 44px !important;
        min-width: 44px !important;
    }

    .routine-action-grid .routine-action-edit {
        grid-area: edit !important;
        width: 44px !important;
        min-width: 44px !important;
    }

    .routine-action-grid .routine-action-delete {
        grid-area: delete !important;
        width: 44px !important;
        min-width: 44px !important;
    }

    .routine-action-grid .routine-btn,
    .routine-action-grid .routine-action-delete .routine-btn {
        width: 44px !important;
        min-width: 44px !important;
        padding: 4px 2px !important;
        font-size: 10px !important;
        line-height: 1.15 !important;
        white-space: nowrap !important;
    }

    .routine-badge {
        white-space: nowrap !important;
    }

    body > aside,
    aside[class*="sidebar"],
    .sidebar,
    .admin-sidebar,
    .side-menu,
    .side-nav,
    nav[class*="sidebar"] {
        flex: 0 0 auto !important;
        flex-shrink: 0 !important;
        min-width: fit-content;
        word-break: keep-all !important;
        overflow-wrap: normal !important;
    }


    /* Rev9: 列ラベル重なり対策・進捗率計算変更・横スクロール強化 */
    .routine-wrap,
    .routine-card,
    .routine-routine-list,
    .routine-routine-card {
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .routine-routine-card {
        overflow-x: auto !important;
        overflow-y: visible !important;
    }

    .routine-table-wrap {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
        padding-bottom: 14px !important;
        scrollbar-width: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }

    .routine-table-wrap::-webkit-scrollbar {
        height: 14px !important;
    }

    .routine-table-wrap::-webkit-scrollbar-track {
        background: #e2e8f0 !important;
        border-radius: 999px !important;
    }

    .routine-table-wrap::-webkit-scrollbar-thumb {
        background: #64748b !important;
        border-radius: 999px !important;
    }

    .routine-card-table {
        width: 1590px !important;
        min-width: 1590px !important;
        table-layout: fixed !important;
    }

    .routine-card-table th {
        white-space: normal !important;
        word-break: keep-all !important;
        overflow-wrap: normal !important;
        line-height: 1.35 !important;
        vertical-align: middle !important;
        padding: 8px 6px !important;
        text-align: center !important;
    }

    .routine-card-table td {
        vertical-align: middle !important;
    }

    .routine-card-table th:nth-child(1),
    .routine-card-table td:nth-child(1),
    .routine-card-table .routine-item-cell {
        width: 380px !important;
        min-width: 380px !important;
        max-width: 380px !important;
    }

    .routine-card-table th:nth-child(2),
    .routine-card-table td:nth-child(2) {
        width: 96px !important;
        min-width: 96px !important;
        max-width: 96px !important;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        width: 98px !important;
        min-width: 98px !important;
        max-width: 98px !important;
    }

    .routine-card-table th:nth-child(4),
    .routine-card-table td:nth-child(4) {
        width: 110px !important;
        min-width: 110px !important;
        max-width: 110px !important;
    }

    .routine-card-table th:nth-child(5),
    .routine-card-table td:nth-child(5) {
        width: 90px !important;
        min-width: 90px !important;
        max-width: 90px !important;
    }

    .routine-card-table th:nth-child(6),
    .routine-card-table td:nth-child(6) {
        width: 110px !important;
        min-width: 110px !important;
        max-width: 110px !important;
    }

    .routine-card-table th:nth-child(7),
    .routine-card-table td:nth-child(7) {
        width: 76px !important;
        min-width: 76px !important;
        max-width: 76px !important;
    }

    .routine-card-table th:nth-child(8),
    .routine-card-table td:nth-child(8) {
        width: 96px !important;
        min-width: 96px !important;
        max-width: 96px !important;
    }

    .routine-card-table th:nth-child(9),
    .routine-card-table td:nth-child(9) {
        width: 82px !important;
        min-width: 82px !important;
        max-width: 82px !important;
    }

    .routine-card-table th:nth-child(10),
    .routine-card-table td:nth-child(10) {
        width: 74px !important;
        min-width: 74px !important;
        max-width: 74px !important;
    }

    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 126px !important;
        min-width: 126px !important;
        max-width: 126px !important;
    }

    .routine-card-table th:nth-child(12),
    .routine-card-table td:nth-child(12) {
        width: 82px !important;
        min-width: 82px !important;
        max-width: 82px !important;
    }

    .routine-card-table th:nth-child(13),
    .routine-card-table td:nth-child(13) {
        width: 68px !important;
        min-width: 68px !important;
        max-width: 68px !important;
    }

    .routine-card-table th:nth-child(14),
    .routine-card-table td:nth-child(14) {
        width: 106px !important;
        min-width: 106px !important;
        max-width: 106px !important;
    }

    .routine-card-table th:nth-child(15),
    .routine-card-table td:nth-child(15) {
        width: 58px !important;
        min-width: 58px !important;
        max-width: 58px !important;
    }

    .routine-action-grid {
        grid-template-areas:
            "edit move"
            "finish delete" !important;
        width: 96px !important;
    }

    .routine-action-grid .routine-action-edit {
        grid-area: edit !important;
    }

    .routine-action-grid .routine-action-finish {
        grid-area: finish !important;
    }

    .routine-action-grid .routine-action-move {
        grid-area: move !important;
    }

    .routine-action-grid .routine-action-delete {
        grid-area: delete !important;
    }

    .routine-progress-unset {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 78px;
        min-height: 34px;
        padding: 4px 5px;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.25;
        white-space: normal;
    }


    /* Rev10: 横スクロール到達範囲修正・未設定表示を文字列化・カレンダー幅調整 */
    .routine-routine-card {
        overflow: hidden !important;
        max-width: 100% !important;
    }

    .routine-table-wrap {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
        padding-bottom: 16px !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-gutter: stable !important;
    }

    .routine-card-table {
        width: 1680px !important;
        min-width: 1680px !important;
        max-width: none !important;
        table-layout: fixed !important;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        width: 108px !important;
        min-width: 108px !important;
        max-width: 108px !important;
    }

    .routine-card-table th:nth-child(15),
    .routine-card-table td:nth-child(15) {
        width: 78px !important;
        min-width: 78px !important;
        max-width: 78px !important;
    }

    .routine-progress-unset {
        display: block !important;
        width: auto !important;
        min-height: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        color: #64748b !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        line-height: 1.4 !important;
        white-space: normal !important;
    }

    .routine-calendar-icon-btn {
        width: 36px !important;
        min-width: 36px !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        text-align: center !important;
    }


    /* Rev11: 列構成・ホバー・今日の実績・学習履歴 */
    .routine-card-table {
        width: 1460px !important;
        min-width: 1460px !important;
        table-layout: fixed !important;
    }

    .routine-card-table th,
    .routine-card-table td {
        overflow: visible;
    }

    .routine-card-table th:nth-child(1),
    .routine-card-table td:nth-child(1) {
        width: 310px !important;
        min-width: 310px !important;
    }

    .routine-card-table th:nth-child(2),
    .routine-card-table td:nth-child(2) {
        width: 105px !important;
        min-width: 105px !important;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        width: 120px !important;
        min-width: 120px !important;
    }

    .routine-card-table th:nth-child(4),
    .routine-card-table td:nth-child(4) {
        width: 120px !important;
        min-width: 120px !important;
    }

    .routine-card-table th:nth-child(5),
    .routine-card-table td:nth-child(5) {
        width: 96px !important;
        min-width: 96px !important;
    }

    .routine-card-table th:nth-child(6),
    .routine-card-table td:nth-child(6) {
        width: 84px !important;
        min-width: 84px !important;
    }

    .routine-card-table th:nth-child(7),
    .routine-card-table td:nth-child(7) {
        width: 96px !important;
        min-width: 96px !important;
    }

    .routine-card-table th:nth-child(8),
    .routine-card-table td:nth-child(8) {
        width: 180px !important;
        min-width: 180px !important;
    }

    .routine-card-table th:nth-child(9),
    .routine-card-table td:nth-child(9) {
        width: 66px !important;
        min-width: 66px !important;
    }

    .routine-card-table th:nth-child(10),
    .routine-card-table td:nth-child(10) {
        width: 96px !important;
        min-width: 96px !important;
    }

    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 70px !important;
        min-width: 70px !important;
    }

    .routine-item-cell {
        padding-top: 8px !important;
        padding-bottom: 8px !important;
    }

    .routine-item-compact {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .routine-item-main {
        min-width: 0;
        flex: 1;
    }

    .routine-item-line1 {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }

    .routine-tag-mini {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: #eef6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
        padding: 2px 7px;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.2;
    }

    .routine-item-line2 {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .routine-start-date-mini {
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .routine-actual-display {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        font-weight: 900;
        color: #0f172a;
        line-height: 1.15;
    }

    .routine-actual-icon {
        color: #16a34a;
        font-size: 17px;
        line-height: 1;
    }

    .routine-hover {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        overflow: visible;
    }

    .routine-tooltip {
        display: none;
        position: absolute;
        z-index: 10050;
        left: 50%;
        top: calc(100% + 10px);
        transform: translateX(-50%);
        min-width: 190px;
        max-width: 260px;
        padding: 10px 12px;
        border: 1px solid #cfe0f5;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 16px 36px rgba(15, 23, 42, 0.16);
        color: #0f172a;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.7;
        text-align: left;
        white-space: normal;
    }

    .routine-tooltip::before {
        content: "";
        position: absolute;
        left: 50%;
        top: -7px;
        width: 12px;
        height: 12px;
        background: #ffffff;
        border-left: 1px solid #cfe0f5;
        border-top: 1px solid #cfe0f5;
        transform: translateX(-50%) rotate(45deg);
    }

    .routine-hover:hover .routine-tooltip {
        display: block;
    }

    .routine-tooltip-title {
        font-size: 12px;
        font-weight: 900;
        color: #0f376d;
        margin-bottom: 5px;
    }

    .routine-help {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 14px;
        height: 14px;
        margin-left: 4px;
        border-radius: 50%;
        border: 1px solid #94a3b8;
        color: #64748b;
        font-size: 10px;
        font-weight: 900;
        line-height: 1;
        vertical-align: middle;
    }

    .routine-pace {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-weight: 900;
        white-space: nowrap;
    }

    .routine-pace-success { color: #16a34a; }
    .routine-pace-warning { color: #ea580c; }
    .routine-pace-danger { color: #dc2626; }
    .routine-pace-muted { color: #94a3b8; }

    .routine-history-wrap {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }

    .routine-history-cells {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        white-space: nowrap;
    }

    .routine-history-cell {
        width: 13px;
        height: 13px;
        border-radius: 4px;
        display: inline-block;
        border: 1px solid #dbe3ee;
    }

    .routine-history-cell.worked {
        background: #22c55e;
        border-color: #22c55e;
    }

    .routine-history-cell.missed {
        background: #ef4444;
        border-color: #ef4444;
    }

    .routine-history-cell.pending {
        background: #e5e7eb;
        border-color: #e5e7eb;
    }

    .routine-history-summary {
        color: #334155;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
    }



    /* Final UI polish: compact item, stable tooltips, alert states */
    .routine-card,
    .routine-routine-card {
        overflow: visible !important;
    }

    .routine-table-wrap {
        overflow-x: auto !important;
        overflow-y: visible !important;
        padding-bottom: 72px !important;
    }

    .routine-main-table,
    .routine-card-table {
        width: 100% !important;
        min-width: 1320px !important;
    }

    .routine-item-cell {
        padding-top: 7px !important;
        padding-bottom: 7px !important;
    }

    .routine-item-compact {
        gap: 8px !important;
    }

    .routine-item-line1,
    .routine-item-line2 {
        gap: 6px !important;
        margin-bottom: 4px !important;
    }

    .routine-content-icon-btn {
        min-width: 30px !important;
        width: 30px !important;
        height: 26px !important;
        padding: 0 !important;
        font-size: 14px !important;
        line-height: 1 !important;
    }

    .routine-start-date-mini {
        color: #64748b !important;
        font-size: 11px !important;
        font-weight: 800 !important;
    }

    .routine-hover {
        overflow: visible !important;
        isolation: isolate;
    }

    .routine-tooltip {
        z-index: 99999 !important;
        min-width: 220px !important;
        max-width: 300px !important;
        pointer-events: none;
    }

    .routine-history-tooltip-list {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px solid #e5edf6;
    }

    .badge-muted {
        background: #f1f5f9 !important;
        color: #64748b !important;
        border: 1px solid #cbd5e1 !important;
    }

    .badge-caution {
        background: #fef9c3 !important;
        color: #a16207 !important;
        border: 1px solid #fde68a !important;
    }

    .routine-action-grid {
        grid-template-areas:
            "edit move"
            "finish delete" !important;
        justify-items: center !important;
        align-items: center !important;
        width: 98px !important;
        margin: 0 auto !important;
    }

    .routine-card-table th:nth-child(1),
    .routine-card-table td:nth-child(1) {
        width: 28% !important;
        min-width: 300px !important;
    }

    .routine-card-table th:nth-child(8),
    .routine-card-table td:nth-child(8) {
        width: 150px !important;
        min-width: 150px !important;
    }

    .routine-card-table th:nth-child(10),
    .routine-card-table td:nth-child(10) {
        width: 106px !important;
        min-width: 106px !important;
    }

    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 76px !important;
        min-width: 76px !important;
    }

    /* Final requested refinements: compact routine header, no bottom waste, item layout, status signal, full-width balance */
    .routine-routine-list {
        padding: 8px 10px !important;
        gap: 10px !important;
    }

    .routine-routine-card {
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
        overflow: visible !important;
    }

    .routine-routine-header {
        padding: 7px 12px !important;
        min-height: auto !important;
    }

    .routine-routine-title {
        gap: 8px !important;
    }

    .routine-routine-name {
        font-size: 14px !important;
        line-height: 1.15 !important;
    }

    .routine-package-icon {
        width: 36px !important;
        height: 36px !important;
        font-size: 16px !important;
    }

    .routine-table-wrap {
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
    }

    .routine-card-table {
        width: 100% !important;
        min-width: 1180px !important;
        table-layout: fixed !important;
    }

    .routine-card-table th,
    .routine-card-table td {
        padding-top: 7px !important;
        padding-bottom: 7px !important;
    }

    .routine-card-table th:nth-child(1),
    .routine-card-table td:nth-child(1),
    .routine-card-table .routine-item-cell {
        width: 25% !important;
        min-width: 250px !important;
        max-width: none !important;
    }

    .routine-card-table th:nth-child(2),
    .routine-card-table td:nth-child(2) {
        width: 7% !important;
        min-width: 86px !important;
    }

    .routine-card-table th:nth-child(3),
    .routine-card-table td:nth-child(3) {
        width: 8% !important;
        min-width: 98px !important;
    }

    .routine-card-table th:nth-child(4),
    .routine-card-table td:nth-child(4) {
        width: 9% !important;
        min-width: 112px !important;
    }

    .routine-card-table th:nth-child(5),
    .routine-card-table td:nth-child(5) {
        width: 7% !important;
        min-width: 86px !important;
    }

    .routine-card-table th:nth-child(6),
    .routine-card-table td:nth-child(6) {
        width: 7% !important;
        min-width: 84px !important;
    }

    .routine-card-table th:nth-child(7),
    .routine-card-table td:nth-child(7) {
        width: 8% !important;
        min-width: 92px !important;
    }

    .routine-card-table th:nth-child(8),
    .routine-card-table td:nth-child(8) {
        width: 11% !important;
        min-width: 128px !important;
    }

    .routine-card-table th:nth-child(9),
    .routine-card-table td:nth-child(9) {
        width: 6% !important;
        min-width: 70px !important;
    }

    .routine-card-table th:nth-child(10),
    .routine-card-table td:nth-child(10) {
        width: 8% !important;
        min-width: 98px !important;
    }

    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 4% !important;
        min-width: 54px !important;
    }

    .routine-item-cell {
        padding-top: 7px !important;
        padding-bottom: 7px !important;
    }

    .routine-item-compact {
        align-items: center !important;
        gap: 8px !important;
    }

    .routine-item-line1 {
        margin-bottom: 3px !important;
        line-height: 1.2 !important;
    }

    .routine-title {
        font-size: 13px !important;
        font-weight: 900 !important;
        line-height: 1.2 !important;
    }

    .routine-item-meta-line {
        display: flex !important;
        align-items: center !important;
        gap: 5px !important;
        flex-wrap: nowrap !important;
        color: #64748b !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
    }

    .routine-item-meta {
        color: #64748b !important;
        font-weight: 800 !important;
    }

    .routine-meta-separator {
        color: #cbd5e1 !important;
        font-weight: 900 !important;
    }

    .routine-start-date-mini {
        font-size: 12px !important;
        color: #64748b !important;
        font-weight: 800 !important;
    }

    .routine-content-link {
        border: 0 !important;
        background: transparent !important;
        color: #2563eb !important;
        font-size: 15px !important;
        line-height: 1 !important;
        padding: 0 !important;
        margin: 0 !important;
        cursor: pointer !important;
        text-decoration: none !important;
    }

    .routine-content-link:hover {
        transform: translateY(-1px);
    }

    .routine-tag-mini {
        display: none !important;
    }

    .routine-status-signal {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        white-space: nowrap !important;
        font-weight: 900 !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        background: transparent !important;
        border: 0 !important;
        padding: 0 !important;
    }

    .routine-status-success { color: #15803d !important; }
    .routine-status-caution { color: #a16207 !important; }
    .routine-status-warning { color: #c2410c !important; }
    .routine-status-danger { color: #dc2626 !important; }
    .routine-status-none { color: #94a3b8 !important; }

    .routine-action-grid {
        grid-template-areas:
            "edit move"
            "finish delete" !important;
        justify-items: center !important;
        align-items: center !important;
        width: 92px !important;
        margin: 0 auto !important;
        gap: 4px !important;
    }

    .routine-action-grid .routine-btn,
    .routine-action-grid .routine-action-delete .routine-btn {
        width: 42px !important;
        min-width: 42px !important;
        padding: 4px 2px !important;
        font-size: 10px !important;
    }

    .routine-hover .routine-tooltip {
        display: none !important;
    }

    .routine-floating-tooltip {
        position: fixed !important;
        z-index: 2147483647 !important;
        min-width: 220px !important;
        max-width: 320px !important;
        padding: 10px 12px !important;
        border: 1px solid #cfe0f5 !important;
        border-radius: 10px !important;
        background: #ffffff !important;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.18) !important;
        color: #0f172a !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        line-height: 1.7 !important;
        text-align: left !important;
        white-space: normal !important;
        pointer-events: none !important;
    }

    .routine-floating-tooltip .routine-tooltip-title {
        font-size: 12px !important;
        font-weight: 900 !important;
        color: #0f376d !important;
        margin-bottom: 5px !important;
    }


    /* Rev final-2: remaining refinements */
    .routine-routine-list {
        padding-top: 6px !important;
        padding-bottom: 6px !important;
        gap: 10px !important;
    }

    .routine-routine-card {
        padding-bottom: 0 !important;
        margin-bottom: 10px !important;
        overflow: visible !important;
    }

    .routine-routine-header {
        padding: 6px 12px !important;
        min-height: 44px !important;
    }

    .routine-routine-title {
        gap: 8px !important;
        min-height: 0 !important;
    }

    .routine-package-icon {
        width: 34px !important;
        height: 34px !important;
        min-width: 34px !important;
        font-size: 15px !important;
    }

    .routine-routine-name {
        font-size: 14px !important;
        line-height: 1.1 !important;
    }

    .routine-table-wrap {
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
    }

    .routine-card-table {
        width: 100% !important;
        min-width: 1200px !important;
        table-layout: fixed !important;
        margin-bottom: 0 !important;
    }

    .routine-card-table th,
    .routine-card-table td {
        padding-top: 7px !important;
        padding-bottom: 7px !important;
        vertical-align: middle !important;
    }

    .routine-card-table th:nth-child(1),
    .routine-card-table td:nth-child(1),
    .routine-card-table .routine-item-cell {
        width: 24% !important;
        min-width: 245px !important;
    }

    .routine-card-table th:nth-child(4),
    .routine-card-table td:nth-child(4) {
        width: 9% !important;
        min-width: 112px !important;
    }

    .routine-card-table th:nth-child(8),
    .routine-card-table td:nth-child(8) {
        width: 12% !important;
        min-width: 140px !important;
    }

    .routine-card-table th:nth-child(10),
    .routine-card-table td:nth-child(10) {
        width: 8% !important;
        min-width: 96px !important;
    }

    .routine-card-table th:nth-child(11),
    .routine-card-table td:nth-child(11) {
        width: 7% !important;
        min-width: 78px !important;
    }

    .routine-item-line1 {
        margin-bottom: 0 !important;
        line-height: 1.2 !important;
    }

    .routine-item-meta-line {
        margin-top: 5px !important;
        line-height: 1.25 !important;
    }

    .routine-start-date-mini {
        font-size: 12.5px !important;
        font-weight: 800 !important;
    }

    .routine-status-hover {
        cursor: help !important;
    }

    .routine-status-signal {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
        font-weight: 900 !important;
        white-space: nowrap !important;
    }

    .routine-hover .routine-tooltip,
    .routine-hover:hover .routine-tooltip {
        display: none !important;
    }

    .routine-floating-tooltip {
        position: fixed !important;
        z-index: 2147483647 !important;
        pointer-events: none !important;
    }


    /* Ended routine table redesign */
    .routine-ended-table {
        width: 100% !important;
        min-width: 1320px !important;
        table-layout: fixed !important;
    }

    .routine-ended-table th,
    .routine-ended-table td {
        padding-top: 8px !important;
        padding-bottom: 8px !important;
        vertical-align: middle !important;
    }

    .routine-ended-table th:nth-child(1),
    .routine-ended-table td:nth-child(1) { width: 24% !important; min-width: 245px !important; text-align: center; }
    .routine-ended-table td:nth-child(1) { text-align: left; }
    .routine-ended-table th:nth-child(2),
    .routine-ended-table td:nth-child(2) { width: 7% !important; min-width: 86px !important; }
    .routine-ended-table th:nth-child(3),
    .routine-ended-table td:nth-child(3) { width: 8% !important; min-width: 96px !important; }
    .routine-ended-table th:nth-child(4),
    .routine-ended-table td:nth-child(4) { width: 8% !important; min-width: 96px !important; }
    .routine-ended-table th:nth-child(5),
    .routine-ended-table td:nth-child(5) { width: 8% !important; min-width: 96px !important; }
    .routine-ended-table th:nth-child(6),
    .routine-ended-table td:nth-child(6) { width: 7% !important; min-width: 86px !important; }
    .routine-ended-table th:nth-child(7),
    .routine-ended-table td:nth-child(7) { width: 11% !important; min-width: 132px !important; }
    .routine-ended-table th:nth-child(8),
    .routine-ended-table td:nth-child(8) { width: 8% !important; min-width: 96px !important; }
    .routine-ended-table th:nth-child(9),
    .routine-ended-table td:nth-child(9) { width: 19% !important; min-width: 240px !important; }

    .routine-ended-result {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-weight: 900;
        white-space: nowrap;
    }

    .routine-ended-result.success { color: #15803d; }
    .routine-ended-result.danger { color: #dc2626; }
    .routine-ended-result.muted { color: #64748b; }

    .routine-donut.rate-success {
        background: conic-gradient(#16a34a calc(var(--rate) * 1%), #e5e7eb 0) !important;
    }

    .routine-donut.rate-warning {
        background: conic-gradient(#f59e0b calc(var(--rate) * 1%), #e5e7eb 0) !important;
    }

    .routine-donut.rate-danger {
        background: conic-gradient(#ef4444 calc(var(--rate) * 1%), #e5e7eb 0) !important;
    }

    .routine-score-stars {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        font-size: 14px;
        font-weight: 900;
        white-space: nowrap;
    }

    .routine-score-stars .star-on { color: #f59e0b; }
    .routine-score-stars .star-off { color: #cbd5e1; }
    .routine-score-number {
        margin-left: 4px;
        color: #475569;
        font-size: 11px;
        font-weight: 800;
    }

    .routine-comment-cell {
        text-align: left !important;
        font-size: 12px;
        line-height: 1.45;
        color: #334155;
        word-break: break-word;
    }

    .routine-comment-combined {
        display: flex;
        flex-direction: column;
        gap: 6px;
        max-width: 100%;
        text-align: left;
    }

    .routine-comment-line {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 6px;
        align-items: start;
        min-width: 0;
    }

    .routine-comment-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        padding: 2px 6px;
        font-size: 11px;
        font-weight: 900;
        line-height: 1.3;
        white-space: nowrap;
        min-width: 34px;
    }

    .routine-comment-label.self {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .routine-comment-label.teacher {
        background: #dcfce7;
        color: #15803d;
    }

    .routine-comment-text {
        min-width: 0;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }


    .routine-comment-edit-wrap {
        display: none;
    }

    .routine-comment-label-edit {
        border: 0;
        cursor: pointer;
        gap: 3px;
        font-family: inherit;
    }

    .routine-comment-label-edit:hover {
        filter: brightness(0.96);
        box-shadow: 0 0 0 1px rgba(21, 128, 61, 0.18);
    }

    .routine-comment-edit-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 13px;
        height: 13px;
        border-radius: 3px;
        background: rgba(255, 255, 255, 0.72);
        color: #15803d;
        font-size: 10px;
        line-height: 1;
    }


    .routine-tag {
        display: inline-block;
        margin-right: 6px;
        font-size: 12px;
        color: #2563eb;
        white-space: nowrap;
    }


    /* Package add tables: shared stable columns, full card width */
    .routine-package-add-table,
    .routine-item-package-table {
        width: 100% !important;
        min-width: 100% !important;
        table-layout: fixed !important;
    }

    .routine-package-add-table th,
    .routine-package-add-table td,
    .routine-item-package-table th,
    .routine-item-package-table td {
        box-sizing: border-box !important;
    }

    /* Same width: ルーティンID and ルーティンアイテムID */
    .routine-package-add-table th:nth-child(1),
    .routine-package-add-table td:nth-child(1),
    .routine-item-package-table th:nth-child(1),
    .routine-item-package-table td:nth-child(1) {
        width: 9.5% !important;
        min-width: 128px !important;
        max-width: 180px !important;
        white-space: nowrap !important;
    }

    /* Same width: ルーティン and ルーティンアイテム */
    .routine-package-add-table th:nth-child(2),
    .routine-package-add-table td:nth-child(2),
    .routine-item-package-table th:nth-child(2),
    .routine-item-package-table td:nth-child(2) {
        width: 19% !important;
        min-width: 250px !important;
        max-width: 360px !important;
        white-space: normal !important;
    }

    /* Upper table columns */
    .routine-package-add-table th:nth-child(3),
    .routine-package-add-table td:nth-child(3) {
        width: 7% !important;
        min-width: 88px !important;
        white-space: nowrap !important;
    }

    .routine-package-add-table th:nth-child(4),
    .routine-package-add-table td:nth-child(4) {
        width: 28% !important;
        min-width: 340px !important;
    }

    .routine-package-add-table th:nth-child(5),
    .routine-package-add-table td:nth-child(5),
    .routine-package-add-table th:nth-child(6),
    .routine-package-add-table td:nth-child(6),
    .routine-package-add-table th:nth-child(7),
    .routine-package-add-table td:nth-child(7),
    .routine-package-add-table th:nth-child(8),
    .routine-package-add-table td:nth-child(8) {
        width: 9.125% !important;
        min-width: 92px !important;
        white-space: nowrap !important;
    }

    /* Lower table: add destination stays wide */
    .routine-item-package-table th:nth-child(3),
    .routine-item-package-table td:nth-child(3) {
        width: 22% !important;
        min-width: 300px !important;
        max-width: 420px !important;
    }

    .routine-item-package-table td:nth-child(3) .routine-select {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Lower table: compact but safe numeric columns */
    .routine-item-package-table th:nth-child(4),
    .routine-item-package-table td:nth-child(4),
    .routine-item-package-table th:nth-child(5),
    .routine-item-package-table td:nth-child(5) {
        width: 5.2% !important;
        min-width: 64px !important;
        white-space: nowrap !important;
    }

    .routine-item-package-table th:nth-child(6),
    .routine-item-package-table td:nth-child(6) {
        width: 6.4% !important;
        min-width: 82px !important;
        white-space: nowrap !important;
    }

    .routine-item-package-table th:nth-child(7),
    .routine-item-package-table td:nth-child(7) {
        width: 8.5% !important;
        min-width: 112px !important;
        white-space: nowrap !important;
    }

    .routine-item-package-table th:nth-child(8),
    .routine-item-package-table td:nth-child(8) {
        width: 7.5% !important;
        min-width: 100px !important;
        white-space: nowrap !important;
    }

    .routine-item-package-table th:nth-child(9),
    .routine-item-package-table td:nth-child(9) {
        width: 6.2% !important;
        min-width: 82px !important;
        white-space: nowrap !important;
    }

    .routine-item-package-table th:nth-child(10),
    .routine-item-package-table td:nth-child(10) {
        width: auto !important;
        min-width: 110px !important;
        white-space: nowrap !important;
    }


    /* Match routine item package plus button with upper routine plus button */
    .routine-item-package-table .routine-plus-add-btn {
        width: 22px !important;
        height: 22px !important;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        border-radius: 999px !important;
        background: #16a34a !important;
        color: #ffffff !important;
        font-size: 15px !important;
        font-weight: 900 !important;
        line-height: 22px !important;
        cursor: pointer !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-decoration: none !important;
        vertical-align: middle !important;
        appearance: none !important;
        -webkit-appearance: none !important;
    }

    .routine-item-package-table .routine-plus-add-btn:hover {
        background: #15803d !important;
        color: #ffffff !important;
        text-decoration: none !important;
    }


    /* Routine item package search filters: keep all controls on one row */
    .routine-item-search-row {
        display: grid !important;
        grid-template-columns:
            minmax(190px, 1.05fr)
            minmax(230px, 1.25fr)
            minmax(90px, 0.55fr)
            minmax(90px, 0.55fr)
            minmax(90px, 0.55fr)
            minmax(130px, 0.75fr)
            70px
            70px !important;
        gap: 8px !important;
        align-items: center !important;
    }

    .routine-item-search-row .routine-input,
    .routine-item-search-row .routine-select,
    .routine-item-search-row .routine-primary-btn,
    .routine-item-search-row .routine-clear-btn {
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        white-space: nowrap !important;
    }

    .routine-item-search-row select[name="routine_item_add_status"] {
        min-width: 130px !important;
    }

    .routine-item-search-row select[name="routine_item_grade"],
    .routine-item-search-row select[name="routine_item_level"],
    .routine-item-search-row select[name="routine_item_completion_type"] {
        min-width: 90px !important;
    }

</style>

<div class="routine-wrap">

    @if(session('success'))
        <div class="routine-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="routine-card">
        <div class="routine-card-header">
            <div class="routine-card-title-with-action">
                <span>📅 今日のルーティン・ルーティンアイテム</span>
                <button type="button"
                        class="routine-btn routine-new-btn"
                        data-routine-modal-target="#createEmptyRoutineModal">
                    ＋ 新規作成
                </button>
            </div>
        </div>

        <div class="routine-modal" id="createEmptyRoutineModal">
            <div class="routine-modal-dialog routine-modal-dialog-sm">
                <div class="routine-modal-content">
                    <form method="POST" action="{{ route('admin.students.routine.complete', $student) }}">
                        @csrf

                        <div class="routine-modal-header">
                            <h5 class="routine-modal-title">空のルーティンを新規作成</h5>
                            <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                        </div>

                        <div class="routine-modal-body">
                            <div class="routine-form-group">
                                <label class="routine-form-label">ルーティン名</label>
                                <input type="text"
                                       name="routine_name"
                                       class="routine-form-control"
                                       placeholder="例）低学年脳開発基礎パック"
                                       required>
                            </div>
                        </div>

                        <div class="routine-modal-footer">
                            <button type="button"
                                    class="routine-modal-button routine-modal-button-light"
                                    data-routine-modal-close>
                                閉じる
                            </button>
                            <button type="submit"
                                    class="routine-modal-button routine-modal-button-primary">
                                作成
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="routine-routine-list">
            @forelse($activeRoutines as $routine)
                @php $items = ($routine->items ?? collect())->where('is_active', true)->values(); @endphp

                <div class="routine-routine-card">
                    <div class="routine-routine-header">
                        <div class="routine-routine-title">
                            <span class="routine-package-icon">{{ routineIcon($routine->name, 'routine') }}</span>
                            <div>
                                <div class="routine-routine-name">{{ $routine->name }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="routine-table-wrap">
                        <table class="routine-table routine-main-table routine-card-table">
                            <thead>
                                <tr>
                                    <th>ルーティンアイテム</th>
                                    <th>今日の実績</th>
                                    <th>今日のステータス</th>
                                    <th>状態 <span class="routine-help">?</span></th>
                                    <th>達成必要日数 <span class="routine-help">?</span></th>
                                    <th>進捗率 <span class="routine-help">?</span></th>
                                    <th>学習ペース <span class="routine-help">?</span></th>
                                    <th>学習履歴 <span class="routine-help">?</span></th>
                                    <th>補助教材</th>
                                    <th>アクション</th>
                                    <th>カレンダー</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($items->isEmpty())
                                    <tr>
                                        <td colspan="11" class="text-center py-4">
                                            <div>このルーティンに表示できるアイテムはありません。</div>
                                            <form method="POST"
                                                  action="{{ route('admin.students.routine.cancel', $student) }}"
                                                  class="routine-empty-actions"
                                                  onsubmit="return confirm('この空のパックを完全に削除しますか？');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="student_routine_id" value="{{ $routine->id }}">
                                                <button type="submit" class="routine-btn routine-empty-delete-btn">
                                                    空のパックを削除
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @else
                                    @foreach($items as $index => $item)
                            @php
                                $todayStatus = $todayStatuses->get($item->id);
                                $statuses = $allStatuses->get($item->id, collect());
                                $calendarItemStatuses = $calendarStatuses->get($item->id, collect())->keyBy(fn($s) => Carbon::parse($s->target_date)->toDateString());

                                [$statusText, $statusColor] = routineStatusLabel($todayStatus->status ?? 'not_started');
                                [$elapsedDays, $workedDays, $restedDays, $achievedDays] = routineDailyStats($item, $statuses);
                                $minimumDays = routineMinimumDays($item);
                                $progressRate = routineProgressRate($workedDays, $minimumDays);
                                $rate = $minimumDays ? $progressRate : null;
                                $paceData = routinePaceData($elapsedDays, $workedDays, $minimumDays);
                                [$historyCells, $historyDisplayWorkedDays, $historyElapsedDays] = routineHistoryCells($item, $statuses, 7);
                                [$followText, $followColor, $followReason] = routineFollowStatus($minimumDays, $paceData, $todayStatus, $historyCells);

                                $iconClass = match (($item->routine_content_id ?? 0) % 4) {
                                    1 => 'routine-icon-blue',
                                    2 => 'routine-icon-orange',
                                    3 => 'routine-icon-green',
                                    default => 'routine-icon-purple',
                                };
                            @endphp

                            <tr>
                                <td class="routine-item-cell">
                                    <div class="routine-item-compact">
                                        <span class="routine-icon {{ $iconClass }}">{{ routineIcon($item->item_name, 'item') }}</span>
                                        <div class="routine-item-main">
                                            <div class="routine-item-line1">
                                                <span class="routine-title">{{ $item->item_name }}</span>
                                            </div>
                                            <div class="routine-item-line2 routine-item-meta-line">
                                                <span class="routine-item-meta">{{ $item->tag ?? '-' }}</span>
                                                <span class="routine-meta-separator">｜</span>
                                                <span class="routine-start-date-mini">{{ $item->start_date ? Carbon::parse($item->start_date)->format('Y/m/d') : '-' }}開始</span>
                                                <span class="routine-meta-separator">｜</span>
                                                <button type="button" class="routine-content-link" data-routine-modal-target="#contentModal{{ $item->id }}" title="学習内容確認">📖</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-center">
                                    @if(empty($todayStatus?->study_seconds))
                                        <button type="button" class="routine-btn routine-btn-green" data-routine-modal-target="#dailyStatusModal{{ $item->id }}">実績入力</button>
                                    @else
                                        <div class="routine-actual-display">
                                            <span class="routine-actual-icon">◷</span>
                                            <span>{{ routineActualText($item, $todayStatus) }}</span>
                                        </div>
                                    @endif
                                </td>

                                <td class="text-center"><span class="routine-badge badge-{{ $statusColor }}">{{ $statusText }}</span></td>

                                <td class="text-center">
                                    <span class="routine-hover routine-status-hover">
                                        <span class="routine-status-signal routine-status-{{ $followColor }}">{{ $followText }}</span>
                                        <span class="routine-tooltip">
                                            <div class="routine-tooltip-title">状態：{{ $followText }}</div>
                                            {{ $followReason }}<br>
                                            @if($minimumDays && ($paceData['available'] ?? false))
                                                予定進捗：{{ $paceData['planned'] }}%<br>
                                                実績進捗：{{ $paceData['actual'] }}%<br>
                                                差分：{{ ($paceData['diff'] >= 0 ? '+' : '') . $paceData['diff'] }}%
                                            @else
                                                達成必要日数：未設定
                                            @endif
                                        </span>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="routine-hover">
                                        <span>{{ $minimumDays ? $minimumDays . '日' : '未設定' }}</span>
                                        <span class="routine-tooltip">
                                            <div class="routine-tooltip-title">達成必要日数</div>
                                            @if($minimumDays)
                                                目標：{{ $minimumDays }}日<br>
                                                取り組んだ日数：{{ $workedDays }}日<br>
                                                残り必要日数：{{ max(0, $minimumDays - $workedDays) }}日
                                            @else
                                                達成必要日数が未設定です。<br>
                                                ルーティンパッケージ側で required_days を設定してください。
                                            @endif
                                        </span>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="routine-hover">
                                        @if($minimumDays)
                                            <div class="routine-donut" style="--rate: {{ $progressRate }};">
                                                <span>{{ $progressRate }}%</span>
                                            </div>
                                        @else
                                            <span>—</span>
                                        @endif
                                        <span class="routine-tooltip">
                                            <div class="routine-tooltip-title">進捗率の内訳</div>
                                            取り組んだ日数：{{ $workedDays }}日<br>
                                            達成必要日数：{{ $minimumDays ? $minimumDays . '日' : '未設定' }}<br>
                                            進捗率：{{ $minimumDays ? $progressRate . '%' : '—' }}<br>
                                            @if($minimumDays)
                                                目標達成まで残り {{ max(0, $minimumDays - $workedDays) }}日
                                            @else
                                                達成必要日数が未設定のため計算されません。
                                            @endif
                                        </span>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="routine-hover">
                                        <span class="routine-pace routine-pace-{{ $paceData['color'] }}">
                                            <span>{{ $paceData['symbol'] }}</span>
                                            <span>{{ $paceData['label'] }}</span>
                                        </span>
                                        <span class="routine-tooltip">
                                            <div class="routine-tooltip-title">学習ペース</div>
                                            @if($paceData['available'])
                                                予定進捗：{{ $paceData['planned'] }}%<br>
                                                実績進捗：{{ $paceData['actual'] }}%<br>
                                                差分：{{ ($paceData['diff'] >= 0 ? '+' : '') . $paceData['diff'] }}%<br>
                                                ※1日あたりの基準で算出
                                            @else
                                                達成必要日数が未設定のため、学習ペースは計算されません。
                                            @endif
                                        </span>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="routine-hover">
                                        <span class="routine-history-wrap">
                                            <span class="routine-history-cells">
                                                @foreach($historyCells as $historyCell)
                                                    <span class="routine-history-cell {{ $historyCell['class'] }}"></span>
                                                @endforeach
                                            </span>
                                            <span class="routine-history-summary">({{ $workedDays }}日 / {{ $elapsedDays }}日)</span>
                                        </span>
                                        <span class="routine-tooltip">
                                            <div class="routine-tooltip-title">学習履歴の見方</div>
                                            緑：取り組みあり<br>
                                            赤：取り組みなし<br>
                                            表示：直近{{ count($historyCells) }}日<br>
                                            取り組んだ日数：{{ $workedDays }}日 / 経過日数：{{ $elapsedDays }}日<br>
                                            <div class="routine-history-tooltip-list">
                                                @foreach($historyCells as $historyCell)
                                                    <div>{{ $historyCell['date'] }}：{{ $historyCell['class'] === 'worked' ? '○' : '×' }}</div>
                                                @endforeach
                                            </div>
                                        </span>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="routine-material-stack">
                                        @if(!empty($item->routineContent->material_url))
                                            <button type="button" class="routine-btn routine-btn-cyan" data-routine-modal-target="#materialModal{{ $item->id }}">教材</button>
                                        @else
                                            <button type="button" class="routine-btn routine-btn-disabled" disabled>教材</button>
                                        @endif

                                        @if(!empty($item->routineContent->video_url))
                                            <button type="button" class="routine-btn routine-btn-purple" data-routine-modal-target="#videoModal{{ $item->id }}">動画</button>
                                        @else
                                            <button type="button" class="routine-btn routine-btn-disabled" disabled>動画</button>
                                        @endif
                                    </div>
                                </td>

                                <td class="text-center">
                                    <div class="routine-action-grid">
                                        <button type="button"
                                                class="routine-btn routine-btn-gray routine-action-edit"
                                                data-routine-modal-target="#editModal{{ $item->id }}">
                                            編集
                                        </button>

                                        <button type="button"
                                                class="routine-btn routine-btn-gray routine-action-move"
                                                data-routine-modal-target="#moveItemModal{{ $item->id }}">
                                            移動
                                        </button>

                                        <button type="button"
                                                class="routine-btn routine-btn-orange routine-action-finish"
                                                data-routine-modal-target="#finishItemModal{{ $item->id }}">
                                            完了
                                        </button>

                                        <form method="POST"
                                              action="{{ route('admin.students.karte.routines.items.delete', [$student, $item]) }}"
                                              class="routine-action-delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="routine-btn routine-btn-red"
                                                    onclick="return confirm('このルーティンアイテムを削除しますか？')">
                                                削除
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <button type="button" class="routine-btn routine-calendar-icon-btn" data-routine-modal-target="#calendarModal{{ $item->id }}" title="カレンダー">📅</button>
                                </td>
                            </tr>

                            <div class="routine-modal" id="contentModal{{ $item->id }}">
                                <div class="routine-modal-dialog">
                                    <div class="routine-modal-content">
                                        <div class="routine-modal-header">
                                            <h5 class="routine-modal-title">学習内容確認：{{ $item->item_name }}</h5>
                                            <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                        </div>
                                        <div class="routine-modal-body">
                                            <div class="routine-form-group">
                                                <div class="modal-label">コンテンツ名</div>
                                                <div class="modal-value">{{ $item->routineContent->name ?? '-' }}</div>
                                            </div>
                                            <div class="routine-form-group">
                                                <div class="modal-label">達成条件</div>
                                                <div class="modal-value">{!! routineConditionText($item) !!}</div>
                                            </div>
                                            <div class="routine-form-group">
                                                <div class="modal-label">説明</div>
                                                <div class="modal-value">{{ $item->routineContent->description ?? '説明は未設定です。' }}</div>
                                            </div>
                                            <div class="routine-form-group">
                                                <div class="modal-label">メモ</div>
                                                <div class="modal-value">{{ $item->memo ?? 'メモは未設定です。' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="routine-modal" id="materialModal{{ $item->id }}">
                                <div class="routine-modal-dialog routine-modal-dialog-sm">
                                    <div class="routine-modal-content">
                                        <div class="routine-modal-header">
                                            <h5 class="routine-modal-title">教材：{{ $item->item_name }}</h5>
                                            <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                        </div>
                                        <div class="routine-modal-body">
                                            @if(!empty($item->routineContent->material_url))
                                                <a href="{{ $item->routineContent->material_url }}" target="_blank" class="routine-add-btn">教材を開く</a>
                                            @else
                                                <div class="modal-value">教材はまだ登録されていません。</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="routine-modal" id="videoModal{{ $item->id }}">
                                <div class="routine-modal-dialog routine-modal-dialog-sm">
                                    <div class="routine-modal-content">
                                        <div class="routine-modal-header">
                                            <h5 class="routine-modal-title">動画：{{ $item->item_name }}</h5>
                                            <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                        </div>
                                        <div class="routine-modal-body">
                                            @if(!empty($item->routineContent->video_url))
                                                <a href="{{ $item->routineContent->video_url }}" target="_blank" class="routine-primary-btn" style="display:inline-flex;align-items:center;padding:8px 18px;text-decoration:none;">動画を開く</a>
                                            @else
                                                <div class="modal-value">動画はまだ登録されていません。</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="routine-modal" id="dailyStatusModal{{ $item->id }}">
                                <div class="routine-modal-dialog routine-modal-dialog-sm">
                                    <div class="routine-modal-content">
                                        <form method="POST"
                                            action="{{ route('admin.students.karte.routines.items.daily-status', [$student, $item]) }}">
                                            @csrf

                                            <div class="routine-modal-header">
                                                <h5 class="routine-modal-title">実績入力：{{ $item->item_name }}</h5>
                                                <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                            </div>

                                            <div class="routine-modal-body">
                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">今日のステータス</label>
                                                    <select name="status" class="routine-form-select">
                                                        <option value="completed" @selected(($todayStatus->status ?? '') === 'completed')>完了</option>
                                                        <option value="partial" @selected(($todayStatus->status ?? '') === 'partial')>途中終了</option>
                                                        <option value="not_started" @selected(($todayStatus->status ?? 'not_started') === 'not_started')>未着手</option>
                                                    </select>
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">学習時間（分）</label>
                                                    <input type="number"
                                                        name="study_minutes"
                                                        min="0"
                                                        value="{{ !empty($todayStatus?->study_seconds) ? floor($todayStatus->study_seconds / 60) : '' }}"
                                                        class="routine-form-control">
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">コメント</label>
                                                    <textarea name="comment"
                                                            class="routine-form-control">{{ $todayStatus->comment ?? '' }}</textarea>
                                                </div>
                                            </div>

                                            <div class="routine-modal-footer">
                                                <button type="button"
                                                        class="routine-modal-button routine-modal-button-light"
                                                        data-routine-modal-close>
                                                    閉じる
                                                </button>
                                                <button type="submit"
                                                        class="routine-modal-button routine-modal-button-primary">
                                                    保存
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="routine-modal" id="moveItemModal{{ $item->id }}">
                                <div class="routine-modal-dialog routine-modal-dialog-sm">
                                    <div class="routine-modal-content">
                                        <form method="POST"
                                              action="{{ route('admin.students.karte.routines.items.update', [$student, $item]) }}">
                                            @csrf
                                            @method('PUT')

                                            <input type="hidden" name="move_mode" value="1">

                                            <div class="routine-modal-header">
                                                <h5 class="routine-modal-title">ルーティンアイテム移動：{{ $item->item_name }}</h5>
                                                <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                            </div>

                                            <div class="routine-modal-body">
                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">移動先ルーティン</label>
                                                    <select name="move_to_student_routine_id" class="routine-form-select" required>
                                                        @foreach($activeRoutines as $moveRoutine)
                                                            <option value="{{ $moveRoutine->id }}" @selected($moveRoutine->id === $routine->id)>
                                                                {{ $moveRoutine->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="modal-value">
                                                    選択したルーティンへこのルーティンアイテムを移動します。
                                                </div>
                                            </div>

                                            <div class="routine-modal-footer">
                                                <button type="button"
                                                        class="routine-modal-button routine-modal-button-light"
                                                        data-routine-modal-close>
                                                    閉じる
                                                </button>
                                                <button type="submit"
                                                        class="routine-modal-button routine-modal-button-primary">
                                                    移動する
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="routine-modal" id="editModal{{ $item->id }}">
                                <div class="routine-modal-dialog routine-modal-dialog-sm">
                                    <div class="routine-modal-content">
                                        <form method="POST"
                                            action="{{ route('admin.students.karte.routines.items.update', [$student, $item]) }}">
                                            @csrf
                                            @method('PUT')

                                            <div class="routine-modal-header">
                                                <h5 class="routine-modal-title">ルーティンアイテム編集：{{ $item->item_name }}</h5>
                                                <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                            </div>

                                            <div class="routine-modal-body">
                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">達成条件</label>
                                                    <select name="completion_type_id" class="routine-form-select">
                                                        @foreach($completionTypes as $type)
                                                            <option value="{{ $type->id }}" @selected($item->completion_type_id == $type->id)>
                                                                {{ $type->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">目標値</label>
                                                    <input type="number"
                                                        step="0.01"
                                                        name="target_value"
                                                        value="{{ $item->target_value }}"
                                                        class="routine-form-control">
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">目安時間（分）</label>
                                                    <input type="number"
                                                        name="estimated_minutes"
                                                        value="{{ $item->estimated_minutes }}"
                                                        class="routine-form-control">
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">メモ</label>
                                                    <textarea name="memo"
                                                            class="routine-form-control">{{ $item->memo }}</textarea>
                                                </div>
                                            </div>

                                            <div class="routine-modal-footer">
                                                <button type="button"
                                                        class="routine-modal-button routine-modal-button-light"
                                                        data-routine-modal-close>
                                                    閉じる
                                                </button>
                                                <button type="submit"
                                                        class="routine-modal-button routine-modal-button-primary">
                                                    保存
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="routine-modal" id="finishItemModal{{ $item->id }}">
                                <div class="routine-modal-dialog routine-modal-dialog-sm">
                                    <div class="routine-modal-content">
                                        <form method="POST"
                                            action="{{ route('admin.students.routine.finish', $student) }}">
                                            @csrf

                                            <input type="hidden"
                                                name="student_routine_item_id"
                                                value="{{ $item->id }}">

                                            <div class="routine-modal-header">
                                                <h5 class="routine-modal-title">
                                                    🏁 学習完了登録：{{ $item->item_name }}
                                                </h5>

                                                <button type="button"
                                                        class="routine-modal-close"
                                                        data-routine-modal-close>
                                                    ×
                                                </button>
                                            </div>

                                            <div class="routine-modal-body">
                                                <div style="
                                                    background:#fff7ed;
                                                    border:1px solid #fed7aa;
                                                    color:#c2410c;
                                                    padding:12px;
                                                    border-radius:8px;
                                                    margin-bottom:16px;
                                                    font-size:13px;
                                                    line-height:1.6;
                                                ">
                                                    このアイテムは現在のルーティン一覧から消え、
                                                    「終了したルーティン一覧」へ移動します。<br>
                                                    本当に完了として登録しますか？
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">
                                                        自己評価
                                                    </label>

                                                    <select name="self_evaluation_score"
                                                            class="routine-form-select"
                                                            required>
                                                        <option value="5">★★★★★ 5</option>
                                                        <option value="4">★★★★☆ 4</option>
                                                        <option value="3">★★★☆☆ 3</option>
                                                        <option value="2">★★☆☆☆ 2</option>
                                                        <option value="1">★☆☆☆☆ 1</option>
                                                    </select>
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">
                                                        自己評価コメント
                                                    </label>

                                                    <textarea name="self_evaluation_comment"
                                                            class="routine-form-control"></textarea>
                                                </div>

                                                <div class="routine-form-group">
                                                    <label class="routine-form-label">
                                                        先生コメント
                                                    </label>

                                                    <textarea name="teacher_comment"
                                                            class="routine-form-control"></textarea>
                                                </div>
                                            </div>

                                            <div class="routine-modal-footer">
                                                <button type="button"
                                                        class="routine-modal-button routine-modal-button-light"
                                                        data-routine-modal-close>
                                                    閉じる
                                                </button>

                                                <button type="submit"
                                                        class="routine-modal-button routine-modal-button-primary"
                                                        onclick="return confirm('本当にこのルーティンアイテムを学習完了にしますか？')">
                                                    🏁 完了登録
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="routine-modal" id="calendarModal{{ $item->id }}">
                                <div class="routine-modal-dialog">
                                    <div class="routine-modal-content">
                                        <div class="routine-modal-header">
                                            <h5 class="routine-modal-title">カレンダー：{{ $item->item_name }}</h5>
                                            <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                        </div>
                                        <div class="routine-modal-body">
                                            <div class="calendar-grid">
                                                @php
                                                    $calendarStart = $item->start_date
                                                        ? Carbon::parse($item->start_date)->startOfDay()
                                                        : now()->startOfDay();
                                                    $calendarEnd = $item->completed_at
                                                        ? Carbon::parse($item->completed_at)->startOfDay()
                                                        : now()->startOfDay();
                                                @endphp

                                                @while($calendarStart->lte($calendarEnd))
                                                    @php
                                                        $date = $calendarStart->toDateString();
                                                        $dayStatus = $calendarItemStatuses->get($date);
                                                        [$mark, $markColor] = calendarMark($dayStatus->status ?? 'not_started');
                                                    @endphp
                                                    <div class="calendar-cell calendar-cell-wide">
                                                        <div class="calendar-date-wide">{{ routineJapaneseDate($date) }}</div>
                                                        <div class="calendar-mark calendar-{{ $markColor }}">{{ $mark }}</div>
                                                    </div>
                                                    @php $calendarStart->addDay(); @endphp
                                                @endwhile
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="routine-empty-card">現在表示できるルーティンはありません。</div>
            @endforelse
        </div>
    </div>


    <div class="routine-card">
        <div class="routine-card-header">
            <div>📅 終了したルーティンアイテム（直近20件）</div>
        </div>

        <div class="routine-table-wrap">
            <table class="routine-table routine-ended-table">
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
                            [$elapsedDays, $workedDays, $restedDays, $achievedDays] = routineDailyStats($item, $statuses);
                            $minimumDays = routineMinimumDays($item);
                            $progressRate = $minimumDays ? routineProgressRate($workedDays, $minimumDays) : null;
                            $isAchieved = $minimumDays && $workedDays >= $minimumDays;
                            $resultText = $minimumDays ? ($isAchieved ? '🏆 達成' : '⚠ 未達成') : '—';
                            $resultClass = $minimumDays ? ($isAchieved ? 'success' : 'danger') : 'muted';
                            $donutClass = $progressRate === null ? 'rate-danger' : ($progressRate >= 100 ? 'rate-success' : ($progressRate >= 70 ? 'rate-warning' : 'rate-danger'));
                            [$historyCells, $historyWorkedDays, $historyElapsedDays] = routineEndedHistoryCells($item, $statuses, 7);
                            $iconClass = match (($item->routine_content_id ?? 0) % 4) {
                                1 => 'routine-icon-blue',
                                2 => 'routine-icon-orange',
                                3 => 'routine-icon-green',
                                default => 'routine-icon-purple',
                            };
                        @endphp

                        <tr>
                            <td>
                                <div class="routine-item-box">
                                    <span class="routine-icon {{ $iconClass }}">{{ routineIcon($item->item_name, 'item') }}</span>
                                    <div>
                                        <div class="routine-title">{{ $item->item_name }}</div>
                                        <div class="routine-sub">{{ $item->routine?->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="routine-ended-result {{ $resultClass }}">{{ $resultText }}</span>
                            </td>
                            <td class="text-center">
                                {{ $item->start_date ? Carbon::parse($item->start_date)->format('Y/m/d') : '-' }}
                            </td>
                            <td class="text-center">
                                {{ $item->completed_at ? Carbon::parse($item->completed_at)->format('Y/m/d') : '-' }}
                            </td>
                            <td class="text-center">
                                @if($minimumDays)
                                    <span class="routine-hover">
                                        {{ $workedDays }} / {{ $minimumDays }}日
                                        <span class="routine-tooltip">
                                            <span class="routine-tooltip-title">達成状況</span>
                                            取り組み日数：{{ $workedDays }}日<br>
                                            達成必要日数：{{ $minimumDays }}日<br>
                                            達成率：{{ $progressRate }}%
                                        </span>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                @if($progressRate !== null)
                                    <span class="routine-hover">
                                        <span class="routine-donut {{ $donutClass }}" style="--rate: {{ $progressRate }};">
                                            <span>{{ $progressRate }}%</span>
                                        </span>
                                        <span class="routine-tooltip">
                                            <span class="routine-tooltip-title">完了時進捗率</span>
                                            取り組み日数：{{ $workedDays }}日<br>
                                            達成必要日数：{{ $minimumDays }}日<br>
                                            進捗率：{{ $progressRate }}%
                                        </span>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="routine-hover">
                                    <span class="routine-history-wrap">
                                        <span class="routine-history-cells">
                                            @foreach($historyCells as $cell)
                                                <span class="routine-history-cell {{ $cell['class'] }}"></span>
                                            @endforeach
                                        </span>
                                        <span class="routine-history-summary">{{ $historyWorkedDays }}日 / {{ $historyElapsedDays }}日</span>
                                    </span>
                                    <span class="routine-tooltip">
                                        <span class="routine-tooltip-title">学習履歴</span>
                                        🟩 学習あり<br>
                                        🟥 学習なし<br>
                                        <div class="routine-history-tooltip-list">
                                            @foreach($historyCells as $cell)
                                                {{ $cell['date'] }}：{{ $cell['class'] === 'worked' ? '○' : '×' }}<br>
                                            @endforeach
                                        </div>
                                    </span>
                                </span>
                            </td>
                            <td class="text-center">
                                @if(!empty($item->self_evaluation_score))
                                    <span class="routine-score-stars">
                                        @for($i = 1; $i <= 5; $i++)
                                            <span class="{{ $i <= $item->self_evaluation_score ? 'star-on' : 'star-off' }}">★</span>
                                        @endfor
                                        <span class="routine-score-number">({{ $item->self_evaluation_score }})</span>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="routine-comment-cell">
                                <span class="routine-hover">
                                    <span class="routine-comment-combined">
                                        <span class="routine-comment-line">
                                            <span class="routine-comment-label self">本人</span>
                                            <span class="routine-comment-text">{{ $item->self_evaluation_comment ?: '未入力' }}</span>
                                        </span>
                                        <span class="routine-comment-line">
                                            <button type="button"
                                                    class="routine-comment-label teacher routine-comment-label-edit"
                                                    data-routine-modal-target="#teacherCommentModal{{ $item->id }}"
                                                    title="先生コメントを編集">
                                                先生
                                                <span class="routine-comment-edit-icon">✎</span>
                                            </button>
                                            <span class="routine-comment-text">{{ $item->teacher_comment ?: '未入力' }}</span>
                                        </span>
                                    </span>
                                    <span class="routine-tooltip">
                                        <strong>本人コメント（全文）</strong><br>
                                        {{ $item->self_evaluation_comment ?: '未入力' }}<br>
                                        <div style="height:8px;"></div>
                                        <strong>先生コメント（全文）</strong><br>
                                        {{ $item->teacher_comment ?: '未入力' }}
                                    </span>
                                </span>
                            </td>
                        </tr>

                        <div class="routine-modal" id="teacherCommentModal{{ $item->id }}">
                            <div class="routine-modal-dialog routine-modal-dialog-sm">
                                <div class="routine-modal-content">
                                    <form method="POST" action="{{ route('admin.students.karte.routines.items.update', [$student, $item]) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="teacher_comment_mode" value="1">

                                        <div class="routine-modal-header">
                                            <h5 class="routine-modal-title">先生コメント編集：{{ $item->item_name }}</h5>
                                            <button type="button" class="routine-modal-close" data-routine-modal-close>×</button>
                                        </div>

                                        <div class="routine-modal-body">
                                            <div class="routine-form-group">
                                                <label class="routine-form-label">先生コメント</label>
                                                <textarea name="teacher_comment" class="routine-form-control" rows="5">{{ $item->teacher_comment }}</textarea>
                                            </div>
                                        </div>

                                        <div class="routine-modal-footer">
                                            <button type="button" class="routine-modal-button routine-modal-button-light" data-routine-modal-close>閉じる</button>
                                            <button type="submit" class="routine-modal-button routine-modal-button-primary">保存</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4">終了したルーティンアイテムはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($endedRoutineItemsTotal > 20)
            <div class="routine-more">
                <a href="{{ route('admin.students.karte.routines.history', $student) }}" class="routine-btn">
                    すべて見る
                </a>
            </div>
        @endif
    </div>

    <div class="routine-grid">
        <div class="routine-card">
            <div class="routine-card-header">
                <div>➕ ルーティンの追加</div>
            </div>

            <form method="GET" action="">
                <input type="hidden" name="tab" value="routine">

                @php
                    $gradeOrder = ['ALL','PRE','K1','K2','K3','E1','E2','E3','E4','E5','E6','J1','J2','J3','H1','H2','H3','未設定'];
                    $packageGradeValues = collect($packageGrades ?? [])
                        ->flatMap(function ($grade) {
                            if ($grade === null || $grade === '') {
                                return ['未設定'];
                            }
                            if ($grade === 'ALL') {
                                return ['ALL'];
                            }
                            return array_values(array_filter(array_map('trim', explode(',', $grade))));
                        })
                        ->unique()
                        ->values();

                    $packageGradeOptions = collect($gradeOrder)
                        ->filter(fn($grade) => $packageGradeValues->contains($grade))
                        ->values();

                    $levelOrder = ['初級','中級','上級','未設定'];
                    $packageLevelValues = collect($packageLevels ?? [])
                        ->map(fn($level) => ($level === null || $level === '') ? '未設定' : $level)
                        ->unique()
                        ->values();

                    $packageLevelOptions = collect($levelOrder)
                        ->filter(fn($level) => $packageLevelValues->contains($level))
                        ->values();
                @endphp

                <div class="routine-search-row">
                    <input class="routine-input" name="routine_package_id" value="{{ request('routine_package_id') }}" placeholder="例）PKG-0001">
                    <input class="routine-input" name="routine_package_keyword" value="{{ request('routine_package_keyword') }}" placeholder="ルーティン・説明・タグ">

                    <select class="routine-select" name="routine_package_grade">
                        <option value="all">対象学年</option>
                        @foreach($packageGradeOptions as $grade)
                            <option value="{{ $grade }}" @selected(request('routine_package_grade') === $grade)>{{ $grade }}</option>
                        @endforeach
                    </select>

                    <select class="routine-select" name="routine_package_level">
                        <option value="all">対象レベル</option>
                        @foreach($packageLevelOptions as $level)
                            <option value="{{ $level }}" @selected(request('routine_package_level') === $level)>{{ $level }}</option>
                        @endforeach
                    </select>

                    <select class="routine-select" name="routine_package_category">
                        <option value="all">分類</option>
                        @foreach(($packageCategories ?? collect()) as $category)
                            <option value="{{ $category }}" @selected(request('routine_package_category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>

                    <button class="routine-primary-btn" type="submit">検索</button>
                    <a class="routine-clear-btn" href="?tab=routine">クリア</a>
                </div>
            </form>

            <div class="routine-table-wrap">
                <table class="routine-table routine-mini-table routine-package-add-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ルーティンID</th>
                            <th style="min-width:260px;">ルーティン</th>
                            <th style="width:90px;">目安時間</th>
                            <th style="min-width:390px;">一緒に追加するルーティンアイテム</th>
                            <th>対象学年</th>
                            <th style="width:58px;">対象レベル</th>
                            <th style="width:72px;">分類</th>
                            <th style="width:92px;">タグ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routinePackages as $package)
                            @php
                                $gradeLabels = [
                                    'PRE' => '年少より下',
                                    'K1' => '年少',
                                    'K2' => '年中',
                                    'K3' => '年長',
                                    'E1' => '小1',
                                    'E2' => '小2',
                                    'E3' => '小3',
                                    'E4' => '小4',
                                    'E5' => '小5',
                                    'E6' => '小6',
                                    'J1' => '中1',
                                    'J2' => '中2',
                                    'J3' => '中3',
                                    'H1' => '高1',
                                    'H2' => '高2',
                                    'H3' => '高3',
                                ];
                                $rawGrade = $package->target_grade ?? '';
                                $gradeCodes = $rawGrade && $rawGrade !== 'ALL'
                                    ? array_values(array_filter(array_map('trim', explode(',', $rawGrade))))
                                    : [];
                                $gradeText = $rawGrade === 'ALL' ? 'ALL' : ($gradeCodes ? implode('・', $gradeCodes) : '-');
                                $gradeTooltip = $rawGrade === 'ALL'
                                    ? '全学年対象'
                                    : collect($gradeCodes)->map(fn($code) => $code . '：' . ($gradeLabels[$code] ?? $code))->implode('<br>');
                                $packageTags = $package->tag
                                    ? array_values(array_filter(preg_split('/[、,\s]+/u', $package->tag)))
                                    : [];
                                $descriptionText = $package->description ?? '';
                            @endphp
                            <tr>
                                <td class="text-center" style="white-space:nowrap; width:70px;">
                                    <form method="POST" action="{{ route('admin.students.karte.routines.apply-package', $student) }}" class="routine-inline-add-form">
                                        @csrf
                                        <input type="hidden" name="routine_package_id" value="{{ $package->id }}">
                                        <button type="submit" class="routine-plus-add-btn" title="このルーティンを追加">＋</button>
                                        <span class="routine-package-id-text">PKG-{{ str_pad($package->id, 4, '0', STR_PAD_LEFT) }}</span>
                                    </form>
                                </td>
                                <td class="text-left" style="white-space:normal; line-height:1.45; max-width:260px; text-align:left;">
                                    <div class="routine-package-name-wrap">
                                        @if($descriptionText)
                                            <span class="routine-hover">
                                                <span class="routine-description-icon routine-description-icon-inline">📝</span>
                                                <span class="routine-tooltip">
                                                    <span class="routine-tooltip-title">説明</span><br>
                                                    {{ $descriptionText }}
                                                </span>
                                            </span>
                                        @endif

                                        <span class="routine-hover">
                                            <span class="routine-title routine-package-name-text">{{ $package->name }}</span>
                                            <span class="routine-tooltip">
                                                <span class="routine-tooltip-title">ルーティン</span><br>
                                                {{ $package->name }}
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                @php
                                    $packageItemRows = ($routinePackageItems ?? collect())
                                        ->where('routine_package_id', $package->id)
                                        ->values();
                                    $visiblePackageItems = $packageItemRows->take(2);
                                    $hiddenPackageItemCount = max(0, $packageItemRows->count() - $visiblePackageItems->count());

                                    $dailyMinutes = $packageItemRows->sum(fn($packageItem) => (int)($packageItem->estimated_minutes ?? 0));
                                    $totalMinutes = $packageItemRows->sum(function ($packageItem) {
                                        $itemMinutes = (int)($packageItem->estimated_minutes ?? 0);
                                        $itemDays = max(1, (int)($packageItem->required_days ?? 1));
                                        return $itemMinutes * $itemDays;
                                    });
                                    $totalHours = round($totalMinutes / 60, 1);
                                @endphp

                                <td class="text-center">
                                    <span class="routine-hover">
                                        {{ $dailyMinutes }}分/日
                                        <span class="routine-tooltip">
                                            <span class="routine-tooltip-title">学習目安時間</span><br>
                                            1日学習目安時間 {{ $dailyMinutes }}分<br>
                                            総学習目安時間 {{ $totalMinutes }}分（{{ $totalHours }}時間）<br><br>
                                            内訳<br>
                                            ーーーーーー<br>
                                            @foreach($packageItemRows as $packageItem)
                                                @php
                                                    $itemMinutes = (int)($packageItem->estimated_minutes ?? 0);
                                                    $itemDays = max(1, (int)($packageItem->required_days ?? 1));
                                                    $itemTotalMinutes = $itemMinutes * $itemDays;
                                                @endphp
                                                @php
                                                    $itemTotalHours = intval($itemTotalMinutes / 60);
                                                @endphp
                                                ・{{ $packageItem->item_name }}<br>
                                                {{ $itemMinutes }}分/日 × {{ $itemDays }}日 ＝ {{ $itemTotalMinutes }}分（{{ $itemTotalHours }}時間）<br><br>
                                            @endforeach
                                        </span>
                                    </span>
                                </td>
<td class="text-left" style="white-space:normal; line-height:1.45; max-width:430px; text-align:left;">
                                    @if($packageItemRows->isEmpty())
                                        <span class="muted-text">-</span>
                                    @else
                                        <div class="routine-package-items-tags">
                                            @foreach($packageItemRows as $packageItem)
                                                <span class="routine-package-item-tag">{{ $packageItem->item_name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="routine-hover">
                                        @if($rawGrade === 'ALL')
                                            <span class="routine-grade-chip">ALL</span>
                                        @elseif($gradeCodes)
                                            @foreach($gradeCodes as $gradeCode)
                                                <span class="routine-grade-chip">{{ $gradeCode }}</span>
                                            @endforeach
                                        @else
                                            -
                                        @endif
                                        <span class="routine-tooltip">
                                            <span class="routine-tooltip-title">対象学年</span><br>
                                            {!! $gradeTooltip ?: '-' !!}
                                        </span>
                                    </span>
                                </td>
                                <td class="text-center">{{ $package->target_level ?? '-' }}</td>
                                <td class="text-center">{{ $package->category ?? '-' }}</td>
                                <td class="text-center" style="white-space:nowrap;">
                                    @forelse($packageTags as $tag)
                                        <span class="routine-tag" style="display:inline-block; margin-right:6px;">#{{ $tag }}</span>
                                    @empty
                                        -
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">条件に一致するルーティンパッケージはありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="routine-card">
            <div class="routine-card-header">
                <div>➕ ルーティンアイテムを追加</div>
            </div>

            <form method="GET" action="">
                <input type="hidden" name="tab" value="routine">

                <div class="routine-search-row-small routine-item-search-row">
                    <input class="routine-input" name="routine_item_content_id" value="{{ request('routine_item_content_id') }}" placeholder="例）CONT-0001">
                    <input class="routine-input" name="routine_item_keyword" value="{{ request('routine_item_keyword') }}" placeholder="ルーティンアイテム・説明・タグ">

                    <select class="routine-select" name="routine_item_grade">
                        <option value="all">対象学年</option>
                        @foreach(($itemGrades ?? collect()) as $grade)
                            <option value="{{ $grade }}" @selected(request('routine_item_grade') === $grade)>{{ $grade }}</option>
                        @endforeach
                    </select>

                    <select class="routine-select" name="routine_item_level">
                        <option value="all">対象レベル</option>
                        @foreach(($itemLevels ?? collect()) as $level)
                            <option value="{{ $level }}" @selected(request('routine_item_level') === $level)>{{ $level }}</option>
                        @endforeach
                    </select>

                    <select class="routine-select" name="routine_item_completion_type">
                        <option value="all">達成条件</option>
                        @foreach(($itemCompletionTypes ?? collect()) as $completionType)
                            <option value="{{ $completionType->id }}" @selected((string) request('routine_item_completion_type') === (string) $completionType->id)>{{ $completionType->name }}</option>
                        @endforeach
                        @if($hasUnsetItemCompletionType ?? false)
                            <option value="未設定" @selected(request('routine_item_completion_type') === '未設定')>未設定</option>
                        @endif
                    </select>

                    <select class="routine-select" name="routine_item_add_status">
                        <option value="all" @selected(request('routine_item_add_status', 'all') === 'all')>追加状態</option>
                        <option value="not_added" @selected(request('routine_item_add_status') === 'not_added')>未追加のみ</option>
                        <option value="added" @selected(request('routine_item_add_status') === 'added')>追加済のみ</option>
                    </select>

                    <button class="routine-primary-btn" type="submit">検索</button>
                    <a class="routine-clear-btn" href="?tab=routine">クリア</a>
                </div>
            </form>

            <div class="routine-table-wrap">
                <table class="routine-table routine-mini-table routine-item-package-table">
                    <thead>
                        <tr>
                            <th>ルーティンアイテムID</th>
                            <th>ルーティンアイテム</th>
                            <th>追加先ルーティン</th>
                            <th>1日</th>
                            <th>日数</th>
                            <th>総時間</th>
                            <th>達成条件</th>
                            <th>対象学年</th>
                            <th>対象レベル</th>
                            <th>タグ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routinePackageItems as $item)
                            @php
                                $gradeLabels = [
                                    'PRE' => '年少より下',
                                    'K1' => '年少',
                                    'K2' => '年中',
                                    'K3' => '年長',
                                    'E1' => '小1',
                                    'E2' => '小2',
                                    'E3' => '小3',
                                    'E4' => '小4',
                                    'E5' => '小5',
                                    'E6' => '小6',
                                    'J1' => '中1',
                                    'J2' => '中2',
                                    'J3' => '中3',
                                    'H1' => '高1',
                                    'H2' => '高2',
                                    'H3' => '高3',
                                ];

                                $rawItemGrade = $item->target_grade ?? '';
                                $itemGradeCodes = $rawItemGrade && $rawItemGrade !== 'ALL'
                                    ? array_values(array_filter(array_map('trim', explode(',', $rawItemGrade))))
                                    : [];
                                $itemGradeTooltip = $rawItemGrade === 'ALL'
                                    ? '全学年対象'
                                    : collect($itemGradeCodes)->map(fn($code) => $code . '：' . ($gradeLabels[$code] ?? $code))->implode('<br>');

                                $itemTags = $item->tag
                                    ? array_values(array_filter(preg_split('/[、,\s]+/u', $item->tag)))
                                    : [];

                                $itemMinutes = (int)($item->estimated_minutes ?? 0);
                                $itemDays = max(1, (int)($item->required_days ?? 1));
                                $itemTotalMinutes = $itemMinutes * $itemDays;
                                $itemTotalHours = intval($itemTotalMinutes / 60);

                                $targetValue = $item->target_value !== null
                                    ? rtrim(rtrim(number_format((float)$item->target_value, 2), '0'), '.')
                                    : null;
                                $completionName = $item->completionType?->name ?? '-';
                                $completionUnit = $item->completionType?->unit ?? '';
                                $conditionText = $completionName === '先生承認'
                                    ? '先生承認'
                                    : ($targetValue !== null ? $completionName . ' ' . $targetValue . $completionUnit : $completionName);

                                $itemDescription = $item->memo
                                    ?: ($item->routineContent?->description ?? '');
                            @endphp

                            <tr>
                                <td class="text-center" style="white-space:nowrap;">
                                    <form id="routine-item-apply-form-{{ $item->id }}"
                                          method="POST"
                                          action="{{ route('admin.students.karte.routines.apply-item', $student) }}"
                                          onsubmit="if(!document.getElementById('routine-item-target-{{ $item->id }}').value){ alert('追加先ルーティンを選択してください。'); return false; }">
                                        @csrf
                                        <input type="hidden" name="routine_package_item_id" value="{{ $item->id }}">
                                        <input type="hidden" id="routine-item-target-{{ $item->id }}" name="student_routine_id" value="">
                                        <button type="submit" class="routine-plus-add-btn" title="このルーティンアイテムを追加">＋</button>
                                        <span class="routine-package-id-text">CONT-{{ str_pad($item->routine_content_id, 4, '0', STR_PAD_LEFT) }}</span>
                                    </form>
                                </td>

                                <td class="text-left" style="white-space:normal; line-height:1.45; text-align:left;">
                                    @if($itemDescription)
                                        <span class="routine-hover">
                                            <span class="routine-description-icon routine-description-icon-inline">📝</span>
                                            <span class="routine-tooltip">
                                                <span class="routine-tooltip-title">説明</span><br>
                                                {{ $itemDescription }}
                                            </span>
                                        </span>
                                    @endif
                                    <span class="routine-title routine-package-name-text">{{ $item->item_name }}</span>
                                </td>

                                <td class="text-center">
                                    <select class="routine-select"
                                            style="width:100%; min-width:0;"
                                            onchange="document.getElementById('routine-item-target-{{ $item->id }}').value = this.value;">
                                        <option value="">選択</option>
                                        @foreach(($activeRoutines ?? collect()) as $routine)
                                            <option value="{{ $routine->id }}">{{ $routine->name ?? ('RT-' . $routine->id) }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="text-center" style="white-space:nowrap;">{{ $itemMinutes }}分</td>
                                <td class="text-center" style="white-space:nowrap;">{{ $itemDays }}日</td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <span class="routine-hover">
                                        {{ $itemTotalMinutes }}分
                                        <span class="routine-tooltip">
                                            <span class="routine-tooltip-title">総目安時間</span><br>
                                            {{ $itemMinutes }}分/日 × {{ $itemDays }}日 ＝ {{ $itemTotalMinutes }}分（{{ $itemTotalHours }}時間）
                                        </span>
                                    </span>
                                </td>

                                <td class="text-center">{{ $conditionText }}</td>

                                <td class="text-center">
                                    <span class="routine-hover">
                                        @if($rawItemGrade === 'ALL')
                                            <span class="routine-grade-chip">ALL</span>
                                        @elseif($itemGradeCodes)
                                            @foreach($itemGradeCodes as $gradeCode)
                                                <span class="routine-grade-chip">{{ $gradeCode }}</span>
                                            @endforeach
                                        @else
                                            -
                                        @endif
                                        <span class="routine-tooltip">
                                            <span class="routine-tooltip-title">対象学年</span><br>
                                            {!! $itemGradeTooltip ?: '-' !!}
                                        </span>
                                    </span>
                                </td>

                                <td class="text-center">{{ $item->target_level ?? '-' }}</td>

                                <td class="text-center" style="white-space:nowrap;">
                                    @forelse($itemTags as $tag)
                                        <span class="routine-tag" style="display:inline-block; margin-right:6px;">#{{ $tag }}</span>
                                    @empty
                                        -
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4">条件に一致するルーティンアイテムはありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-routine-modal-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = button.getAttribute('data-routine-modal-target');
            const modal = document.querySelector(target);

            if (modal) {
                modal.classList.add('is-open');
            }
        });
    });

    document.querySelectorAll('[data-routine-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modal = button.closest('.routine-modal');

            if (modal) {
                modal.classList.remove('is-open');
            }
        });
    });

    document.querySelectorAll('.routine-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('is-open');
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.routine-modal.is-open').forEach(function (modal) {
                modal.classList.remove('is-open');
            });
        }
    });


    const floatingTooltip = document.createElement('div');
    floatingTooltip.className = 'routine-floating-tooltip';
    floatingTooltip.style.display = 'none';
    document.body.appendChild(floatingTooltip);

    function positionRoutineTooltip(anchor) {
        const rect = anchor.getBoundingClientRect();
        const tooltipRect = floatingTooltip.getBoundingClientRect();
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        let top = rect.bottom + 10;

        if (left < 8) {
            left = 8;
        }

        if (left + tooltipRect.width > window.innerWidth - 8) {
            left = window.innerWidth - tooltipRect.width - 8;
        }

        if (top + tooltipRect.height > window.innerHeight - 8) {
            top = rect.top - tooltipRect.height - 10;
        }

        if (top < 8) {
            top = 8;
        }

        floatingTooltip.style.left = left + 'px';
        floatingTooltip.style.top = top + 'px';
    }

    document.querySelectorAll('.routine-hover').forEach(function (hover) {
        const tooltip = hover.querySelector('.routine-tooltip');

        if (!tooltip) {
            return;
        }

        hover.addEventListener('mouseenter', function () {
            floatingTooltip.innerHTML = tooltip.innerHTML;
            floatingTooltip.style.display = 'block';
            positionRoutineTooltip(hover);
        });

        hover.addEventListener('mousemove', function () {
            positionRoutineTooltip(hover);
        });

        hover.addEventListener('mouseleave', function () {
            floatingTooltip.style.display = 'none';
            floatingTooltip.innerHTML = '';
        });
    });

    window.addEventListener('scroll', function () {
        floatingTooltip.style.display = 'none';
    }, true);

    window.addEventListener('resize', function () {
        floatingTooltip.style.display = 'none';
    });

});
</script>
