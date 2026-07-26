<?php

namespace App\Services\Routine;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 教育ルーティン横断管理Service。
 * 役割: 生徒別・ルーティン別割当表示、期間別達成集計、履歴検索、一括割当。
 * Blade内DB検索とN+1を避けるため、必要な集計を一括クエリで構築する。
 */
class EducationRoutineService
{
    public const LOW_STUDY_SECONDS = 30;
    public const LOW_ACCURACY_RATE = 60;
    public const CONSECUTIVE_MISSED_DAYS = 2;

    public function __construct(private readonly StudentRoutineReadService $studentRoutineReadService) {}

    public function assignmentScreen(Request $request): array
    {
        $view = in_array($request->string('view')->toString(), ['students', 'routines'], true)
            ? $request->string('view')->toString()
            : 'students';

        $studentPerPage = $this->perPage($request, 'student_per_page', 50);
        $studentBaseQuery = $this->studentQuery($request);
        $assignmentStudents = (clone $studentBaseQuery)->limit(500)->get();
        $students = $studentBaseQuery
            ->paginate($studentPerPage, ['*'], 'student_page')
            ->withQueryString();

        $packages = $this->packageQuery($request)
            ->paginate(20, ['*'], 'package_page')
            ->withQueryString();

        $selectedStudentId = (int) ($request->integer('student_id') ?: optional($students->first())->id);
        $selectedPackageId = (int) ($request->integer('package_id') ?: optional($packages->first())->id);

        $selectedStudent = $selectedStudentId ? $this->studentDetail($selectedStudentId) : null;
        $selectedPackage = $selectedPackageId ? $this->packageDetail($selectedPackageId, $request) : null;
        $this->attachPackageItems($packages);
        $this->markPackagesAssignedToStudent($packages, $selectedStudentId);

        $packageMode = in_array($request->string('package_mode')->toString(), ['all', 'popular', 'recent'], true)
            ? $request->string('package_mode')->toString()
            : 'all';

        return compact('view', 'students', 'assignmentStudents', 'packages', 'selectedStudent', 'selectedPackage', 'packageMode') + [
            'schools' => $this->options('schools'),
            'grades' => $this->options('grades'),
            'completionTypes' => $this->options('routine_completion_types'),
        ];
    }

    public function statusScreen(Request $request): array
    {
        $schools = $this->options('schools');
        $schoolId = $request->integer('school_id') ?: (int) optional($schools->first())->id;

        $subjectRequest = $request->duplicate();
        $subjectRequest->merge([
            'school_id' => $schoolId ?: null,
            'student_keyword' => $request->input('student_keyword'),
        ]);
        $subjects = $this->studentQuery($subjectRequest)
            ->paginate(50)
            ->withQueryString();

        $selectedId = $request->integer('student_id') ?: (int) optional($subjects->first())->id;
        $selected = $selectedId ? $this->studentDetail($selectedId) : null;

        return [
            'schools' => $schools,
            'schoolId' => $schoolId,
            'subjects' => $subjects,
            'selectedId' => $selectedId,
            'selected' => $selected,
            'activeRoutines' => $selected->routines ?? collect(),
            'todayStatuses' => $selected->today_statuses ?? collect(),
            'allStatuses' => $selected->all_statuses ?? collect(),
            'calendarStatuses' => $selected->calendar_statuses ?? collect(),
        ];
    }

    public function historyScreen(Request $request): array
    {
        $perPage = $this->perPage($request, 'per_page', 50);
        $rows = $this->itemHistory($request, $perPage);

        return [
            'rows' => $rows,
            'schools' => $this->options('schools'),
            'grades' => $this->options('grades'),
        ];
    }

    private function studentQuery(Request $request)
    {
        $today = today()->toDateString();
        $activeRoutineStats = DB::table('student_routines as active_sr')
            ->leftJoin('student_routine_items as active_sri', function ($join): void {
                $join->on('active_sri.student_routine_id', '=', 'active_sr.id')
                    ->where('active_sri.is_active', true);
            })
            ->where('active_sr.is_active', true)
            ->where(function ($query) use ($today): void {
                $query->whereNull('active_sr.start_date')->orWhereDate('active_sr.start_date', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('active_sr.end_date')->orWhereDate('active_sr.end_date', '>=', $today);
            })
            ->select('active_sr.student_id')
            ->selectRaw('COUNT(DISTINCT active_sr.id) as routine_count')
            ->selectRaw('COUNT(DISTINCT active_sri.id) as item_count')
            ->groupBy('active_sr.student_id');

        $attentionStats = DB::table('student_routines as att_sr')
            ->join('student_routine_items as att_i', 'att_i.student_routine_id', '=', 'att_sr.id')
            ->leftJoin('student_routine_daily_statuses as att_d', function ($join) use ($today): void {
                $join->on('att_d.student_routine_item_id', '=', 'att_i.id')
                    ->whereDate('att_d.target_date', $today);
            })
            ->where('att_sr.is_active', true)
            ->where('att_i.is_active', true)
            ->select('att_sr.student_id')
            ->selectRaw("SUM(CASE WHEN att_d.id IS NULL OR COALESCE(att_d.status, '未着手') IN ('未着手','not_started','unstarted') THEN 1 ELSE 0 END) as attention_count")
            ->groupBy('att_sr.student_id');

        $query = DB::table('students as s')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoinSub($activeRoutineStats, 'ars', fn ($join) => $join->on('ars.student_id', '=', 's.id'))
            ->leftJoinSub($attentionStats, 'ats', fn ($join) => $join->on('ats.student_id', '=', 's.id'))
            ->select('s.id', 's.student_code', 's.last_name', 's.first_name', 'sc.name as school_name', 'g.name as grade_name')
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name")
            ->selectRaw('COALESCE(ars.routine_count, 0) as routine_count')
            ->selectRaw('COALESCE(ars.item_count, 0) as item_count')
            ->selectRaw('COALESCE(ats.attention_count, 0) as attention_count');

        if ($keyword = trim($request->string('student_keyword')->toString())) {
            $query->where(function ($where) use ($keyword): void {
                $where->where('s.student_code', 'like', "%{$keyword}%")
                    ->orWhere('s.last_name', 'like', "%{$keyword}%")
                    ->orWhere('s.first_name', 'like', "%{$keyword}%");
            });
        }
        if ($request->filled('school_id')) {
            $query->where('s.school_id', $request->integer('school_id'));
        }
        if ($request->filled('grade_id')) {
            $query->where('s.grade_id', $request->integer('grade_id'));
        }
        if ($request->boolean('attention_only')) {
            $query->whereRaw('COALESCE(ats.attention_count, 0) > 0');
        }
        if ($request->filled('assignment_status')) {
            match ($request->string('assignment_status')->toString()) {
                'assigned' => $query->whereRaw('COALESCE(ars.routine_count, 0) > 0'),
                'unassigned' => $query->whereRaw('COALESCE(ars.routine_count, 0) = 0'),
                default => null,
            };
        }

        return match ($request->string('student_sort')->toString()) {
            'student_code' => $query->orderBy('s.student_code'),
            'grade' => $query->orderBy('g.name')->orderBy('s.last_name'),
            'attention' => $query->orderByDesc('attention_count')->orderBy('s.last_name'),
            default => $query->orderBy('s.last_name')->orderBy('s.first_name'),
        };
    }

    /**
     * 割当候補ルーティンをDB側で検索・並び替え・ページングする。
     * 割当回数・最近割当表示では、student_routinesをルーティン単位で集約し、
     * 同一ルーティンの重複を除いた最新割当だけを並び順へ利用する。
     */
    private function packageQuery(Request $request)
    {
        $mode = in_array($request->string('package_mode')->toString(), ['all', 'popular', 'recent'], true)
            ? $request->string('package_mode')->toString()
            : 'all';

        /*
         * 割当回数と最新割当IDは、ルーティン構成アイテムのJOINとは分離して集計する。
         * これにより構成アイテム件数によって割当回数が水増しされず、
         * 「割当回数が多い」がstudent_routinesの実件数どおりに並ぶ。
         */
        $assignmentStats = DB::table('student_routines as stats_sr')
            ->whereNotNull('stats_sr.routine_package_id')
            ->select('stats_sr.routine_package_id')
            ->selectRaw('COUNT(stats_sr.id) as assignment_count')
            ->selectRaw('MAX(stats_sr.id) as latest_assignment_id')
            ->groupBy('stats_sr.routine_package_id');

        /*
         * 構成アイテム数・合計時間・推奨日数も別サブクエリで集計する。
         * routine_packages本体をGROUP BYしないため、PostgreSQLでも安定して並び替えできる。
         */
        $itemStats = DB::table('routine_package_items as stats_rpi')
            ->select('stats_rpi.routine_package_id')
            ->selectRaw('COUNT(stats_rpi.id) as item_count')
            ->selectRaw('COALESCE(SUM(stats_rpi.estimated_minutes), 0) as total_minutes')
            ->selectRaw('COALESCE(MAX(stats_rpi.required_days), 0) as recommended_days')
            ->groupBy('stats_rpi.routine_package_id');

        $q = DB::table('routine_packages as rp')
            ->leftJoinSub($itemStats, 'item_stats', function ($join): void {
                $join->on('item_stats.routine_package_id', '=', 'rp.id');
            })
            ->leftJoinSub($assignmentStats, 'assignment_stats', function ($join): void {
                $join->on('assignment_stats.routine_package_id', '=', 'rp.id');
            })
            ->select('rp.*')
            ->selectRaw('COALESCE(item_stats.item_count, 0) as item_count')
            ->selectRaw('COALESCE(item_stats.total_minutes, 0) as total_minutes')
            ->selectRaw('COALESCE(item_stats.recommended_days, 0) as recommended_days')
            ->selectRaw('COALESCE(assignment_stats.assignment_count, 0) as assignment_count')
            ->selectRaw('assignment_stats.latest_assignment_id');

        if (Schema::hasColumn('routine_packages', 'is_active')) {
            $q->where('rp.is_active', true);
        }

        if ($keyword = trim($request->string('package_keyword')->toString())) {
            $q->where(function ($query) use ($keyword): void {
                $query->where('rp.name', 'like', "%{$keyword}%")
                    ->orWhere('rp.description', 'like', "%{$keyword}%");

                if (Schema::hasColumn('routine_packages', 'package_code')) {
                    $query->orWhere('rp.package_code', 'like', "%{$keyword}%");
                }
                if (Schema::hasColumn('routine_packages', 'search_tags')) {
                    $query->orWhere('rp.search_tags', 'like', "%{$keyword}%");
                }

                $query->orWhereExists(function ($itemQuery) use ($keyword): void {
                    $itemQuery->selectRaw('1')
                        ->from('routine_package_items as search_rpi')
                        ->leftJoin('routine_contents as search_rc', 'search_rc.id', '=', 'search_rpi.routine_content_id')
                        ->whereColumn('search_rpi.routine_package_id', 'rp.id')
                        ->where(function ($nameQuery) use ($keyword): void {
                            $nameQuery->where('search_rpi.item_name', 'like', "%{$keyword}%")
                                ->orWhere('search_rc.name', 'like', "%{$keyword}%");
                        });
                });
            });
        }

        if ($request->filled('package_grade')) {
            $q->where('rp.target_grade', 'like', '%'.$request->string('package_grade')->toString().'%');
        }
        if ($request->filled('package_level') && Schema::hasColumn('routine_packages', 'target_level')) {
            $q->where('rp.target_level', $request->string('package_level')->toString());
        }
        if ($request->filled('package_active') && Schema::hasColumn('routine_packages', 'is_active')) {
            $q->where('rp.is_active', $request->string('package_active')->toString() === 'active');
        }

        if ($mode === 'popular') {
            // 一度も割り当てられていないルーティンは「割当回数が多い」には表示しない。
            $q->whereNotNull('assignment_stats.assignment_count')
                ->where('assignment_stats.assignment_count', '>', 0)
                ->orderByDesc('assignment_stats.assignment_count')
                ->orderBy('rp.sort_order')
                ->orderBy('rp.id');
        } elseif ($mode === 'recent') {
            $q->whereNotNull('assignment_stats.latest_assignment_id')
                ->orderByDesc('assignment_stats.latest_assignment_id')
                ->orderBy('rp.sort_order')
                ->orderBy('rp.id');
        } else {
            $q->orderBy('rp.sort_order')->orderBy('rp.id');
        }

        return $q;
    }

    private function attachPackageItems(LengthAwarePaginator $packages): void
    {
        $ids = collect($packages->items())->pluck('id')->filter();
        if ($ids->isEmpty()) return;
        $items = DB::table('routine_package_items as i')
            ->leftJoin('routine_contents as rc','rc.id','=','i.routine_content_id')
            ->leftJoin('routine_completion_types as ct','ct.id','=','i.completion_type_id')
            ->leftJoin('learning_pages as lp','lp.routine_content_id','=','i.routine_content_id')
            ->whereIn('i.routine_package_id',$ids)
            ->select('i.*','rc.name as master_name','rc.learning_page_status')
            ->selectRaw($this->completionTypeSelect('ct'))
            ->selectRaw(Schema::hasColumn('learning_pages','publish_status') ? 'lp.publish_status' : 'NULL as publish_status')
            ->orderBy('i.order_no')->get()->groupBy('routine_package_id');
        foreach ($packages->items() as $package) $package->items = $items->get($package->id, collect())->values();
    }

    private function studentDetail(int $id): ?object
    {
        $student = DB::table('students as s')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->select('s.*', 'sc.name as school_name', 'g.name as grade_name')
            ->where('s.id', $id)
            ->first();

        if (!$student) {
            return null;
        }

        $routines = $this->studentRoutineReadService->activeForStudent($id);
        $itemIds = $routines->flatMap->items->pluck('id')->values();
        $todayStatuses = $itemIds->isEmpty()
            ? collect()
            : DB::table('student_routine_daily_statuses')
                ->whereIn('student_routine_item_id', $itemIds)
                ->whereDate('target_date', today())
                ->get()
                ->keyBy('student_routine_item_id');
        $dailyStatuses = $itemIds->isEmpty()
            ? collect()
            : DB::table('student_routine_daily_statuses')
                ->whereIn('student_routine_item_id', $itemIds)
                ->whereDate('target_date', '<=', today())
                ->orderBy('target_date')
                ->get()
                ->groupBy('student_routine_item_id');

        $progress = $itemIds->isEmpty()
            ? collect()
            : DB::table('student_routine_daily_statuses')
                ->whereIn('student_routine_item_id', $itemIds)
                ->select('student_routine_item_id')
                ->selectRaw("COUNT(*) FILTER (WHERE status IN ('完了','達成','completed')) as completed_days")
                ->groupBy('student_routine_item_id')
                ->get()
                ->keyBy('student_routine_item_id');

        $routines->each(function ($routine) use ($todayStatuses, $dailyStatuses, $progress): void {
            $routine->items->each(function ($item) use ($todayStatuses, $dailyStatuses, $progress): void {
                $today = $todayStatuses->get($item->id);
                $completedDays = (int) optional($progress->get($item->id))->completed_days;
                $item->progress = $item->required_days
                    ? min(100, (int) round($completedDays / max(1, $item->required_days) * 100))
                    : ($item->completed_at ? 100 : 0);

                $itemStatuses = $dailyStatuses->get($item->id, collect());
                $startDate = $item->start_date ? Carbon::parse($item->start_date)->startOfDay() : today()->startOfDay();
                $elapsedDays = max(1, $startDate->diffInDays(today()->startOfDay()) + 1);
                $expectedRate = $item->required_days
                    ? min(100, (int) round($elapsedDays / max(1, (int) $item->required_days) * 100))
                    : null;
                $paceDiff = $expectedRate === null ? null : $item->progress - $expectedRate;

                $statusByDate = $itemStatuses->keyBy(fn ($row) => Carbon::parse($row->target_date)->toDateString());
                $missedDays = 0;
                for ($date = today()->startOfDay(); $date->gte($startDate); $date->subDay()) {
                    $rawStatus = mb_strtolower(trim((string) optional($statusByDate->get($date->toDateString()))->status));
                    if (in_array($rawStatus, ['completed', 'complete', 'done', '完了', '達成', 'partial', 'in_progress', '学習中', '一部完了'], true)) {
                        break;
                    }
                    $missedDays++;
                    if ($missedDays >= 3) {
                        break;
                    }
                }

                $follow = RoutineStatusPresenter::followStatus(
                    $item->required_days ? (int) $item->required_days : null,
                    $paceDiff,
                    $missedDays,
                    $today->status ?? null,
                    (bool) $item->is_active,
                    $item->completed_at
                );
                $item->follow_status = $follow['label'];
                $item->follow_status_reason = $follow['reason'];
                $item->follow_badge_class = $follow['badge_class'];
                $item->today_status = $today->status ?? null;
                $item->today_study_seconds = (int) ($today->study_seconds ?? 0);
                $item->completed_days = $completedDays;
                $item->elapsed_days = $elapsedDays;
                $item->expected_rate = $expectedRate;
                $item->pace_diff = $paceDiff;
                $workedStatuses = ['completed', 'complete', 'done', '完了', '達成', 'partial', 'in_progress', '学習中', '一部完了'];
                $item->history_worked_days = $itemStatuses->filter(function ($row) use ($workedStatuses): bool {
                    $status = mb_strtolower(trim((string) ($row->status ?? '')));
                    return in_array($status, $workedStatuses, true);
                })->pluck('target_date')->unique()->count();

                // 生徒カルテと同じく、直近7日を緑（取組あり）／赤（未実施）で表示する。
                $historyStart = today()->copy()->subDays(6);
                if ($startDate->gt($historyStart)) {
                    $historyStart = $startDate->copy();
                }
                $historyCells = collect();
                for ($date = $historyStart->copy(); $date->lte(today()); $date->addDay()) {
                    $row = $statusByDate->get($date->toDateString());
                    $rawStatus = mb_strtolower(trim((string) ($row->status ?? '')));
                    $historyCells->push([
                        'date' => $date->format('m/d'),
                        'class' => in_array($rawStatus, $workedStatuses, true) ? 'worked' : 'missed',
                    ]);
                }
                $item->history_cells = $historyCells;

                $item->display_status = RoutineStatusPresenter::label(null, (bool) $item->is_active, $item->completed_at);
                $item->badge_class = RoutineStatusPresenter::badgeClass($item->display_status);
            });
            $routine->completed_items = $routine->items->filter(fn ($item) => $item->completed_at || $item->progress >= 100)->values();
            $routine->active_items = $routine->items->reject(fn ($item) => $item->completed_at || $item->progress >= 100)->values();
            $routine->completed_count = $routine->completed_items->count();
            $routine->item_count = $routine->items->count();
            $routine->progress = $routine->item_count
                ? (int) round($routine->items->avg('progress'))
                : 0;
            $routine->display_status = RoutineStatusPresenter::label(null, (bool) $routine->is_active, null);
        });

        // 生徒カルテと同じ生データ集合を教育画面へ渡す。
        // 画面側で独自計算せず、同じ日次状態から同じ進捗・学習履歴・カレンダーを描画する。
        $student->routines = $routines;
        $student->today_statuses = $todayStatuses;
        $student->all_statuses = $dailyStatuses;
        $student->calendar_statuses = $dailyStatuses;

        return $student;
    }

    private function packageDetail(int $id, Request $request): ?object
    {
        $package = DB::table('routine_packages')->where('id', $id)->first();
        if (!$package) {
            return null;
        }

        $package->items = DB::table('routine_package_items as i')
            ->leftJoin('routine_contents as rc', 'rc.id', '=', 'i.routine_content_id')
            ->leftJoin('routine_completion_types as ct', 'ct.id', '=', 'i.completion_type_id')
            ->leftJoin('learning_pages as lp', 'lp.routine_content_id', '=', 'i.routine_content_id')
            ->where('i.routine_package_id', $id)
            ->select('i.*', 'rc.name as master_name', 'rc.learning_page_status')
            ->selectRaw($this->completionTypeSelect('ct'))
            ->selectRaw(Schema::hasColumn('learning_pages', 'publish_status') ? 'lp.publish_status' : 'NULL as publish_status')
            ->orderBy('i.order_no')
            ->get();

        $assignments = DB::table('student_routines as sr')
            ->join('students as s', 's.id', '=', 'sr.student_id')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->where('sr.routine_package_id', $id)
            ->select('sr.*', 's.student_code', 's.last_name', 's.first_name', 'sc.name as school_name', 'g.name as grade_name')
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name")
            ->orderByDesc('sr.id')
            ->get();

        if ($keyword = trim($request->string('assigned_student_keyword')->toString())) {
            $assignments = $assignments->filter(fn ($row) => str_contains(mb_strtolower($row->student_name.' '.$row->student_code), mb_strtolower($keyword)))->values();
        }
        if ($request->filled('assigned_school_id')) {
            $schoolName = optional($this->options('schools')->firstWhere('id', $request->integer('assigned_school_id')))->name;
            $assignments = $assignments->where('school_name', $schoolName)->values();
        }

        $package->current_students = $assignments
            ->filter(fn ($row) => (bool) $row->is_active)
            ->groupBy('student_id')
            ->map->first()
            ->values();
        $package->past_students = $assignments
            ->reject(fn ($row) => (bool) $row->is_active)
            ->values();
        $package->assignment_count = $assignments->count();
        $package->total_minutes = (int) $package->items->sum('estimated_minutes');
        $package->recommended_days = (int) $package->items->max('required_days');

        return $package;
    }

    private function markPackagesAssignedToStudent(LengthAwarePaginator $packages, int $studentId): void
    {
        if (!$studentId) {
            return;
        }
        $ids = collect($packages->items())->pluck('id');
        $assigned = DB::table('student_routines')
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->whereIn('routine_package_id', $ids)
            ->pluck('routine_package_id')
            ->flip();
        foreach ($packages->items() as $package) {
            $package->assigned_to_selected_student = $assigned->has($package->id);
        }
    }

    public function assignPackage(array $data, ?int $userId): array
    {
        $package=DB::table('routine_packages')->where('id',$data['routine_package_id'])->first();
        if (!$package) throw new \InvalidArgumentException('対象ルーティンが見つかりません。');
        $templateItems=DB::table('routine_package_items as rpi')->leftJoin('routine_contents as rc','rc.id','=','rpi.routine_content_id')->where('rpi.routine_package_id',$package->id)->orderBy('rpi.order_no')->select('rpi.*')->selectRaw('COALESCE(rc.estimated_days, rpi.required_days) as assignment_required_days')->selectRaw('COALESCE(rc.daily_learning_minutes, rpi.estimated_minutes) as assignment_estimated_minutes')->get();
        $created=[];
        DB::transaction(function()use($data,$userId,$package,$templateItems,&$created){
            foreach(array_unique($data['student_ids']) as $studentId){
                $exists = DB::table('student_routines')->where('student_id',$studentId)->where('routine_package_id',$package->id)->where('is_active',true)->exists();
                if ($exists && empty($data['allow_duplicate'])) continue;
                $rid=DB::table('student_routines')->insertGetId(array_filter([
                    'student_id'=>$studentId,'routine_package_id'=>$package->id,'name'=>$data['name']?:$package->name,
                    'description'=>$data['description']??$package->description,'start_date'=>$data['start_date'],'end_date'=>$data['end_date']??null,
                    'is_active'=>true,'created_by'=>$userId,'created_at'=>now(),'updated_at'=>now(),
                ],fn($v)=>$v!==null));
                foreach($templateItems as $item){
                    $custom=$data['items'][$item->id]??[]; if(array_key_exists('enabled',$custom)&&!$custom['enabled'])continue;
                    DB::table('student_routine_items')->insert([
                        'student_routine_id'=>$rid,'routine_package_item_id'=>$item->id,'routine_content_id'=>$item->routine_content_id,
                        'item_name'=>$custom['item_name']??$item->item_name,'target_grade'=>$item->target_grade,'target_level'=>$item->target_level,'tag'=>$item->tag,
                        'completion_type_id'=>$custom['completion_type_id']??$item->completion_type_id,'target_value'=>$custom['target_value']??$item->target_value,
                        'required_days'=>$custom['required_days']??$item->assignment_required_days,'estimated_minutes'=>$custom['estimated_minutes']??$item->assignment_estimated_minutes,
                        'order_no'=>$custom['order_no']??$item->order_no,'is_required'=>$custom['is_required']??$item->is_required,'is_active'=>true,
                        'memo'=>$custom['memo']??$item->memo,'start_date'=>$data['start_date'],'created_at'=>now(),'updated_at'=>now(),
                    ]);
                } $created[]=$rid;
            }
        });
        return ['ids'=>$created,'message'=>count($created).'名へ「'.$package->name.'」を割り当てました。'];
    }

    /**
     * 生徒ルーティンの割当を取り消す。
     * 学習履歴がない場合のみ割当データを物理削除し、履歴がある場合は参照整合性を守るため停止扱いにする。
     *
     * 参照DB: student_routines, student_routine_items, student_routine_daily_statuses, routine_study_sessions
     * 更新DB: student_routines, student_routine_items
     */
    public function cancelAssignment(int $studentRoutineId, ?int $userId): array
    {
        $routine = DB::table('student_routines')->where('id', $studentRoutineId)->first();
        abort_unless($routine, 404);

        $itemIds = DB::table('student_routine_items')
            ->where('student_routine_id', $studentRoutineId)
            ->pluck('id');

        $hasDailyHistory = $itemIds->isNotEmpty()
            && DB::table('student_routine_daily_statuses')->whereIn('student_routine_item_id', $itemIds)->exists();
        $hasStudyHistory = $itemIds->isNotEmpty()
            && Schema::hasTable('routine_study_sessions')
            && DB::table('routine_study_sessions')->whereIn('student_routine_item_id', $itemIds)->exists();
        $hasHistory = $hasDailyHistory || $hasStudyHistory;

        DB::transaction(function () use ($routine, $studentRoutineId, $itemIds, $hasHistory, $userId): void {
            if (!$hasHistory) {
                DB::table('student_routine_items')->where('student_routine_id', $studentRoutineId)->delete();
                DB::table('student_routines')->where('id', $studentRoutineId)->delete();
                return;
            }

            $routineUpdate = ['is_active' => false, 'updated_at' => now()];
            if ($userId && Schema::hasColumn('student_routines', 'updated_by')) {
                $routineUpdate['updated_by'] = $userId;
            }
            if (Schema::hasColumn('student_routines', 'ended_at')) {
                $routineUpdate['ended_at'] = now();
            }
            if (Schema::hasColumn('student_routines', 'end_reason')) {
                $routineUpdate['end_reason'] = 'assignment_cancelled';
            }
            if (Schema::hasColumn('student_routines', 'status')) {
                $routineUpdate['status'] = 'cancelled';
            }
            DB::table('student_routines')->where('id', $studentRoutineId)->update($routineUpdate);

            if ($itemIds->isNotEmpty() && Schema::hasColumn('student_routine_items', 'is_active')) {
                $itemUpdate = ['is_active' => false, 'updated_at' => now()];
                if ($userId && Schema::hasColumn('student_routine_items', 'updated_by')) {
                    $itemUpdate['updated_by'] = $userId;
                }
                DB::table('student_routine_items')->whereIn('id', $itemIds)->update($itemUpdate);
            }
        });

        return [
            'student_id' => (int) $routine->student_id,
            'message' => $hasHistory
                ? '学習履歴があるため、割当データを残したまま停止しました。'
                : 'ルーティンの割当を取り消しました。',
        ];
    }

    /**
     * 割当状態を安全に更新する。現行DBにstatus等が存在する場合のみ補助カラムも更新する。
     */
    public function updateAssignmentState(int $studentRoutineId, string $action, ?int $userId): array
    {
        $routine = DB::table('student_routines')->where('id', $studentRoutineId)->first();
        abort_unless($routine, 404);
        $itemIds = DB::table('student_routine_items')->where('student_routine_id', $studentRoutineId)->pluck('id');

        DB::transaction(function () use ($studentRoutineId, $itemIds, $action, $userId): void {
            $active = $action === 'resume';
            $routineUpdate = ['is_active' => $active, 'updated_at' => now()];
            if (Schema::hasColumn('student_routines', 'status')) {
                $routineUpdate['status'] = match ($action) {
                    'resume' => 'active',
                    'complete' => 'completed',
                    default => 'stopped',
                };
            }
            if ($action === 'complete' && Schema::hasColumn('student_routines', 'completed_at')) {
                $routineUpdate['completed_at'] = now();
            }
            if ($action !== 'resume' && Schema::hasColumn('student_routines', 'ended_at')) {
                $routineUpdate['ended_at'] = now();
            }
            if ($userId && Schema::hasColumn('student_routines', 'updated_by')) {
                $routineUpdate['updated_by'] = $userId;
            }
            DB::table('student_routines')->where('id', $studentRoutineId)->update($routineUpdate);

            if ($itemIds->isNotEmpty()) {
                $itemUpdate = ['is_active' => $active, 'updated_at' => now()];
                if ($action === 'complete' && Schema::hasColumn('student_routine_items', 'completed_at')) {
                    $itemUpdate['completed_at'] = now();
                }
                if ($action === 'resume' && Schema::hasColumn('student_routine_items', 'completed_at')) {
                    $itemUpdate['completed_at'] = null;
                }
                if ($userId && Schema::hasColumn('student_routine_items', 'updated_by')) {
                    $itemUpdate['updated_by'] = $userId;
                }
                DB::table('student_routine_items')->whereIn('id', $itemIds)->update($itemUpdate);
            }
        });

        return [
            'student_id' => (int) $routine->student_id,
            'message' => match ($action) {
                'resume' => 'ルーティンを再開しました。',
                'complete' => 'ルーティンを完了にしました。',
                default => 'ルーティンを停止しました。',
            },
        ];
    }

    private function period(Request $request): array
    {
        $period = $request->string('period')->toString() ?: 'today';
        $today = today();

        return match ($period) {
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek(), $period],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), $period],
            'custom' => [
                Carbon::parse($request->input('from', $today->toDateString())),
                Carbon::parse($request->input('to', $today->toDateString())),
                $period,
            ],
            default => [$today, $today, 'today'],
        };
    }

    private function statusGroupedQuery(Request $request, Carbon $from, Carbon $to, string $view)
    {
        $dailyStats = DB::table('student_routine_daily_statuses as ds')
            ->whereBetween('ds.target_date', [$from->toDateString(), $to->toDateString()])
            ->select('ds.student_routine_item_id')
            ->selectRaw("COUNT(*) FILTER (WHERE COALESCE(ds.status,'未着手') NOT IN ('未着手','not_started','unstarted')) as started_count")
            ->selectRaw("COUNT(*) FILTER (WHERE ds.status IN ('完了','達成','completed')) as completed_count")
            ->selectRaw('COALESCE(SUM(ds.study_seconds),0) as study_seconds')
            ->selectRaw('MAX(ds.studied_at) as last_studied_at')
            ->selectRaw("COUNT(*) FILTER (WHERE ds.approved_at IS NULL AND ds.status IN ('完了','達成','completed')) as approval_pending")
            ->groupBy('ds.student_routine_item_id');

        $sessionStats = DB::table('routine_study_sessions as ss')
            ->leftJoin('routine_study_results as rsr', 'rsr.routine_study_session_id', '=', 'ss.id')
            ->whereBetween(DB::raw('DATE(ss.started_at)'), [$from->toDateString(), $to->toDateString()])
            ->select('ss.student_routine_item_id')
            ->selectRaw('AVG(rsr.accuracy_rate) as accuracy_rate')
            ->selectRaw('COUNT(rsr.id) as result_count')
            ->groupBy('ss.student_routine_item_id');

        $query = DB::table('student_routine_items as i')
            ->join('student_routines as r', 'r.id', '=', 'i.student_routine_id')
            ->join('students as s', 's.id', '=', 'r.student_id')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('routine_packages as rp', 'rp.id', '=', 'r.routine_package_id')
            ->leftJoinSub($dailyStats, 'dstat', fn ($join) => $join->on('dstat.student_routine_item_id', '=', 'i.id'))
            ->leftJoinSub($sessionStats, 'sstat', fn ($join) => $join->on('sstat.student_routine_item_id', '=', 'i.id'))
            ->where('r.is_active', true)
            ->where('i.is_active', true)
            ->where(function ($where) use ($to): void {
                $where->whereNull('r.start_date')->orWhereDate('r.start_date', '<=', $to->toDateString());
            })
            ->where(function ($where) use ($from): void {
                $where->whereNull('r.end_date')->orWhereDate('r.end_date', '>=', $from->toDateString());
            });

        [$keySelect, $groupBy] = match ($view) {
            'routines' => [
                [
                    'r.id as group_id',
                    'r.name as name',
                    DB::raw("COALESCE(rp.package_code, 'ID:' || r.id::text) as code"),
                    DB::raw("COALESCE(rp.target_grade, '対象学年未設定') as grade_name"),
                    DB::raw("NULL as school_name"),
                ],
                ['r.id', 'r.name', 'rp.package_code', 'rp.target_grade'],
            ],
            'schools' => [
                [
                    DB::raw('COALESCE(sc.id, 0) as group_id'),
                    DB::raw("COALESCE(sc.name, '教室未設定') as name"),
                    DB::raw('NULL as code'),
                    DB::raw('NULL as grade_name'),
                    DB::raw("COALESCE(sc.name, '教室未設定') as school_name"),
                ],
                ['sc.id', 'sc.name'],
            ],
            default => [
                [
                    's.id as group_id',
                    DB::raw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as name"),
                    's.student_code as code',
                    'g.name as grade_name',
                    DB::raw("COALESCE(sc.name, '教室未設定') as school_name"),
                ],
                ['s.id', 's.last_name', 's.first_name', 's.student_code', 'g.name', 'sc.name'],
            ],
        };

        $query->select($keySelect)
            ->selectRaw('COUNT(DISTINCT i.id) as target_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(dstat.started_count,0) > 0 THEN i.id END) as started_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(dstat.completed_count,0) > 0 THEN i.id END) as completed_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(dstat.started_count,0) = 0 THEN i.id END) as unstarted_count')
            ->selectRaw('COALESCE(SUM(dstat.study_seconds),0) as study_seconds')
            ->selectRaw('AVG(sstat.accuracy_rate) FILTER (WHERE COALESCE(sstat.result_count,0) > 0) as accuracy_rate')
            ->selectRaw('SUM(COALESCE(dstat.approval_pending,0)) as approval_pending')
            ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(dstat.started_count,0) = 0 OR (sstat.accuracy_rate IS NOT NULL AND sstat.accuracy_rate < ?) THEN i.id END) as attention_count', [self::LOW_ACCURACY_RATE])
            ->groupBy(...$groupBy);

        if ($keyword = trim($request->string('keyword')->toString())) {
            $query->where(function ($where) use ($keyword, $view): void {
                if ($view === 'students') {
                    $where->where('s.student_code', 'like', "%{$keyword}%")
                        ->orWhere('s.last_name', 'like', "%{$keyword}%")
                        ->orWhere('s.first_name', 'like', "%{$keyword}%");
                } elseif ($view === 'routines') {
                    $where->where('r.name', 'like', "%{$keyword}%")
                        ->orWhere('rp.package_code', 'like', "%{$keyword}%");
                } else {
                    $where->where('sc.name', 'like', "%{$keyword}%");
                }
            });
        }
        if ($request->filled('school_id')) {
            $query->where('s.school_id', $request->integer('school_id'));
        }
        if ($request->filled('grade_id')) {
            $query->where('s.grade_id', $request->integer('grade_id'));
        }
        if ($request->boolean('attention_only')) {
            $query->havingRaw('COUNT(DISTINCT CASE WHEN COALESCE(dstat.started_count,0) = 0 OR (sstat.accuracy_rate IS NOT NULL AND sstat.accuracy_rate < ?) THEN i.id END) > 0', [self::LOW_ACCURACY_RATE]);
        }

        $sort = $request->string('sort')->toString();
        return match ($sort) {
            'unstarted' => $query->orderByDesc('unstarted_count')->orderBy('name'),
            'attention' => $query->orderByDesc('attention_count')->orderBy('name'),
            'engagement' => $query->orderByRaw('(COUNT(DISTINCT CASE WHEN COALESCE(dstat.started_count,0) > 0 THEN i.id END)::decimal / NULLIF(COUNT(DISTINCT i.id),0)) DESC NULLS LAST')->orderBy('name'),
            default => $query->orderBy('name'),
        };
    }

    private function statusTotals(Collection $rows): object
    {
        $target = (int) $rows->sum('target_count');
        $started = (int) $rows->sum('started_count');
        $completed = (int) $rows->sum('completed_count');
        $accuracyValues = $rows->pluck('accuracy_rate')->filter(fn ($value) => $value !== null);

        return (object) [
            'target_count' => $target,
            'started_count' => $started,
            'completed_count' => $completed,
            'unstarted_count' => (int) $rows->sum('unstarted_count'),
            'engagement_rate' => $target ? round($started / $target * 100, 1) : null,
            'completion_rate' => $target ? round($completed / $target * 100, 1) : null,
            'study_seconds' => (int) $rows->sum('study_seconds'),
            'accuracy_rate' => $accuracyValues->isNotEmpty() ? round((float) $accuracyValues->avg(), 1) : null,
            'attention_count' => (int) $rows->sum('attention_count'),
        ];
    }

    private function calendarRows(Request $request, Carbon $from, Carbon $to, int $perPage): LengthAwarePaginator
    {
        $studentQuery = DB::table('students as s')
            ->join('student_routines as r', 'r.student_id', '=', 's.id')
            ->join('student_routine_items as i', 'i.student_routine_id', '=', 'r.id')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->where('r.is_active', true)
            ->where('i.is_active', true)
            ->select('s.id', 's.student_code', 'sc.name as school_name', 'g.name as grade_name')
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as name")
            ->distinct();

        if ($keyword = trim($request->string('keyword')->toString())) {
            $studentQuery->where(fn ($where) => $where->where('s.student_code', 'like', "%{$keyword}%")->orWhere('s.last_name', 'like', "%{$keyword}%")->orWhere('s.first_name', 'like', "%{$keyword}%"));
        }
        if ($request->filled('school_id')) {
            $studentQuery->where('s.school_id', $request->integer('school_id'));
        }
        if ($request->filled('grade_id')) {
            $studentQuery->where('s.grade_id', $request->integer('grade_id'));
        }

        $paginator = $studentQuery->orderBy('name')->paginate($perPage)->withQueryString();
        $studentIds = collect($paginator->items())->pluck('id');
        $statuses = $studentIds->isEmpty() ? collect() : DB::table('student_routine_daily_statuses as d')
            ->join('student_routine_items as i', 'i.id', '=', 'd.student_routine_item_id')
            ->join('student_routines as r', 'r.id', '=', 'i.student_routine_id')
            ->whereIn('d.student_id', $studentIds)
            ->whereBetween('d.target_date', [$from->toDateString(), $to->toDateString()])
            ->select('d.student_id', 'd.target_date', 'd.status', 'i.item_name')
            ->get()
            ->groupBy(['student_id', 'target_date']);

        foreach ($paginator->items() as $student) {
            $student->days = collect();
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $records = collect(data_get($statuses, $student->id.'.'.$date->toDateString(), []));
                $labels = $records->map(fn ($record) => RoutineStatusPresenter::label($record->status))->values();
                $label = $records->isEmpty()
                    ? '対象外'
                    : ($labels->every(fn ($value) => $value === '完了') ? '完了' : ($labels->contains('未着手') ? ($labels->contains('完了') ? '一部完了' : '未着手') : '一部完了'));
                $student->days->put($date->toDateString(), (object) [
                    'label' => $label,
                    'items' => $records->pluck('item_name')->values(),
                ]);
            }
        }

        return $paginator;
    }

    private function itemHistory(Request $request, int $perPage): LengthAwarePaginator
    {
        $daily = DB::table('student_routine_daily_statuses as d')
            ->select('d.student_routine_item_id')
            ->selectRaw("COUNT(DISTINCT CASE WHEN d.status IN ('completed','complete','done','完了','達成') THEN d.target_date END) as achieved_days")
            ->selectRaw('COALESCE(SUM(d.study_seconds),0) as study_seconds')
            ->selectRaw('MAX(COALESCE(d.studied_at, d.updated_at)) as last_activity_at')
            ->groupBy('d.student_routine_item_id');

        $results = DB::table('routine_study_sessions as ss')
            ->leftJoin('routine_study_results as rr', 'rr.routine_study_session_id', '=', 'ss.id')
            ->select('ss.student_routine_item_id')
            ->selectRaw('AVG(rr.accuracy_rate) as average_accuracy_rate')
            ->groupBy('ss.student_routine_item_id');

        $query = DB::table('student_routine_items as i')
            ->join('student_routines as sr', 'sr.id', '=', 'i.student_routine_id')
            ->join('students as s', 's.id', '=', 'sr.student_id')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('routine_packages as rp', 'rp.id', '=', 'sr.routine_package_id')
            ->leftJoin('routine_contents as rc', 'rc.id', '=', 'i.routine_content_id')
            ->leftJoinSub($daily, 'ds', fn ($join) => $join->on('ds.student_routine_item_id', '=', 'i.id'))
            ->leftJoinSub($results, 'rs', fn ($join) => $join->on('rs.student_routine_item_id', '=', 'i.id'))
            ->select(
                'i.id', 'i.item_name', 'i.is_required', 'i.start_date', 'i.completed_at', 'i.is_active',
                'i.required_days', 'i.memo', 'i.routine_content_id',
                'sr.name as routine_name', 'rp.package_code',
                'rc.name as master_item_name',
                's.student_code', 'sc.name as school_name', 'g.name as grade_name',
                'ds.achieved_days', 'ds.study_seconds', 'ds.last_activity_at', 'rs.average_accuracy_rate'
            )
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name")
            ->selectRaw(Schema::hasColumn('routine_contents', 'code') ? 'rc.code as content_code' : 'NULL as content_code');

        if ($keyword = trim($request->string('keyword')->toString())) {
            $query->where(function ($where) use ($keyword): void {
                $where->where('s.student_code', 'like', "%{$keyword}%")
                    ->orWhere('s.last_name', 'like', "%{$keyword}%")
                    ->orWhere('s.first_name', 'like', "%{$keyword}%")
                    ->orWhere('sr.name', 'like', "%{$keyword}%")
                    ->orWhere('rp.package_code', 'like', "%{$keyword}%")
                    ->orWhere('i.item_name', 'like', "%{$keyword}%")
                    ->orWhere('rc.name', 'like', "%{$keyword}%");
                if (Schema::hasColumn('routine_contents', 'code')) {
                    $where->orWhere('rc.code', 'like', "%{$keyword}%");
                }
            });
        }
        if ($request->filled('school_id')) $query->where('s.school_id', $request->integer('school_id'));
        if ($request->filled('grade_id')) $query->where('s.grade_id', $request->integer('grade_id'));
        if ($request->filled('from')) $query->whereDate(DB::raw('COALESCE(i.start_date, sr.start_date)'), '>=', $request->date('from')->toDateString());
        if ($request->filled('to')) $query->whereDate(DB::raw('COALESCE(i.completed_at, sr.end_date, CURRENT_DATE)'), '<=', $request->date('to')->toDateString());
        if ($request->filled('required_type')) $query->where('i.is_required', $request->string('required_type')->toString() === 'required');
        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'active' => $query->where('i.is_active', true)->whereNull('i.completed_at'),
                'completed' => $query->whereNotNull('i.completed_at'),
                'stopped' => $query->where('i.is_active', false)->whereNull('i.completed_at'),
                default => null,
            };
        }

        $sort = $request->string('sort')->toString() ?: 'updated_desc';
        match ($sort) {
            'student_asc' => $query->orderBy('s.last_name')->orderBy('s.first_name')->orderBy('i.id'),
            'start_desc' => $query->orderByDesc(DB::raw('COALESCE(i.start_date, sr.start_date)'))->orderByDesc('i.id'),
            'progress_desc' => $query->orderByRaw('CASE WHEN i.required_days IS NULL OR i.required_days = 0 THEN -1 ELSE LEAST(100, ROUND(COALESCE(ds.achieved_days,0)::numeric / i.required_days * 100)) END DESC')->orderByDesc('i.id'),
            'activity_desc' => $query->orderByDesc('ds.last_activity_at')->orderByDesc('i.id'),
            default => $query->orderByDesc(DB::raw('COALESCE(i.completed_at, i.updated_at, i.created_at)'))->orderByDesc('i.id'),
        };

        $paginator = $query->paginate($perPage)->withQueryString();

        foreach ($paginator->items() as $row) {
            $row->achieved_days = (int) ($row->achieved_days ?? 0);
            $row->progress_rate = $row->required_days ? min(100, (int) round($row->achieved_days / max(1, (int) $row->required_days) * 100)) : null;
            $row->display_status = $row->completed_at ? '完了' : ((bool) $row->is_active ? '実施中' : '停止');
            $row->badge_class = $row->completed_at ? 'green' : ((bool) $row->is_active ? 'blue' : 'gray');
            $row->effective_start_date = $row->start_date;
            $row->study_time_label = $this->formatDuration((int) ($row->study_seconds ?? 0));
        }
        return $paginator;
    }

    public function itemHistoryDetail(int $studentRoutineItemId): array
    {
        $item = DB::table('student_routine_items as i')
            ->join('student_routines as sr', 'sr.id', '=', 'i.student_routine_id')
            ->join('students as s', 's.id', '=', 'sr.student_id')
            ->leftJoin('routine_contents as rc', 'rc.id', '=', 'i.routine_content_id')
            ->select('i.*', 'sr.name as routine_name', 'rc.name as master_item_name', 's.student_code')
            ->selectRaw(Schema::hasColumn('routine_contents', 'code') ? 'rc.code as content_code' : 'NULL as content_code')
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name")
            ->where('i.id', $studentRoutineItemId)
            ->first();
        abort_unless($item, 404);

        $daily = DB::table('student_routine_daily_statuses')
            ->where('student_routine_item_id', $studentRoutineItemId)
            ->orderBy('target_date')
            ->get();
        $sessions = Schema::hasTable('routine_study_sessions')
            ? DB::table('routine_study_sessions as ss')
                ->leftJoin('routine_study_results as rr', 'rr.routine_study_session_id', '=', 'ss.id')
                ->where('ss.student_routine_item_id', $studentRoutineItemId)
                ->select('ss.*', 'rr.total_questions', 'rr.correct_answers', 'rr.incorrect_answers', 'rr.accuracy_rate', 'rr.score')
                ->orderByDesc('ss.started_at')->get()
            : collect();

        $start = $item->start_date ? Carbon::parse($item->start_date)->startOfDay() : today()->startOfDay();
        $end = $item->completed_at ? Carbon::parse($item->completed_at)->startOfDay() : today()->startOfDay();
        if ($end->lt($start)) $end = $start->copy();
        $dailyByDate = $daily->keyBy(fn ($row) => Carbon::parse($row->target_date)->toDateString());
        $worked = ['completed','complete','done','完了','達成','partial','in_progress','学習中','一部完了'];
        $calendar = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $row = $dailyByDate->get($date->toDateString());
            $calendar[] = [
                'date' => $date->format('Y/m/d'),
                'mark' => in_array(mb_strtolower(trim((string) ($row->status ?? ''))), $worked, true) ? '○' : '×',
                'worked' => in_array(mb_strtolower(trim((string) ($row->status ?? ''))), $worked, true),
            ];
        }

        return [
            'title' => $item->item_name ?: $item->master_item_name ?: 'ルーティンアイテム詳細',
            'item' => $item,
            'daily' => $daily,
            'sessions' => $sessions,
            'calendar' => $calendar,
        ];
    }

    private function routineHistory(Request $request, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('student_routines as sr')
            ->join('students as s', 's.id', '=', 'sr.student_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('routine_packages as rp', 'rp.id', '=', 'sr.routine_package_id')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('student_routine_items as i', 'i.student_routine_id', '=', 'sr.id')
            ->leftJoin('student_routine_daily_statuses as d', 'd.student_routine_item_id', '=', 'i.id')
            ->select('sr.id', 's.student_code', 'g.name as grade_name', 'sc.name as school_name', 'sr.name', 'rp.name as template_name', 'rp.package_code', 'sr.start_date', 'sr.end_date', 'sr.is_active', 'sr.self_evaluation_score', 'sr.teacher_comment')
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name")
            ->selectRaw('COUNT(DISTINCT i.id) as item_count')
            ->selectRaw('COUNT(DISTINCT d.target_date) as studied_days')
            ->selectRaw('COALESCE(MAX(i.required_days),0) as target_days')
            ->selectRaw('AVG(d.achievement_rate) as achievement_rate')
            ->selectRaw('COALESCE(SUM(d.study_seconds),0) as study_seconds')
            ->groupBy('sr.id', 's.student_code', 's.last_name', 's.first_name', 'g.name', 'sc.name', 'rp.name', 'rp.package_code');

        $this->applyHistoryCommonFilters($query, $request, 'sr', 's', 'sc', 'g', 'rp', 'i');
        if (!$request->filled('status')) {
            $query->where('sr.is_active', false);
        } elseif ($request->string('status')->toString() === 'active') {
            $query->where('sr.is_active', true);
        } elseif ($request->string('status')->toString() === 'ended') {
            $query->where('sr.is_active', false);
        }

        return $query->orderByDesc('sr.id')->paginate($perPage)->withQueryString();
    }

    private function dailyHistory(Request $request, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('student_routine_daily_statuses as d')
            ->join('student_routine_items as i', 'i.id', '=', 'd.student_routine_item_id')
            ->join('student_routines as sr', 'sr.id', '=', 'i.student_routine_id')
            ->join('students as s', 's.id', '=', 'd.student_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('routine_packages as rp', 'rp.id', '=', 'sr.routine_package_id')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.approved_by')
            ->select('d.*', 's.student_code', 'g.name as grade_name', 'i.item_name', 'sr.name as routine_name', 'rp.package_code', 'sc.name as school_name', 'u.name as approver_name')
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name");
        $this->applyHistoryCommonFilters($query, $request, 'sr', 's', 'sc', 'g', 'rp', 'i', 'd');
        if ($request->filled('approval_status')) {
            match ($request->string('approval_status')->toString()) {
                'approved' => $query->whereNotNull('d.approved_at'),
                'pending' => $query->whereNull('d.approved_at')->whereIn('d.status', ['完了', '達成', 'completed']),
                'not_required' => $query->whereNull('d.approved_at')->whereNotIn('d.status', ['完了', '達成', 'completed']),
                default => null,
            };
        }
        if ($request->filled('accuracy_min')) {
            $query->where('d.achievement_rate', '>=', $request->float('accuracy_min'));
        }
        if ($request->filled('accuracy_max')) {
            $query->where('d.achievement_rate', '<=', $request->float('accuracy_max'));
        }
        return $query->orderByDesc('d.target_date')->orderByDesc('d.id')->paginate($perPage)->withQueryString();
    }

    private function resultHistory(Request $request, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('routine_study_sessions as ss')
            ->join('student_routine_items as i', 'i.id', '=', 'ss.student_routine_item_id')
            ->join('student_routines as sr', 'sr.id', '=', 'i.student_routine_id')
            ->join('students as s', 's.id', '=', 'ss.student_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('schools as sc', 'sc.id', '=', 's.school_id')
            ->leftJoin('routine_packages as rp', 'rp.id', '=', 'sr.routine_package_id')
            ->leftJoin('routine_study_results as res', 'res.routine_study_session_id', '=', 'ss.id')
            ->select('ss.*', 's.student_code', 'g.name as grade_name', 'sc.name as school_name', 'rp.package_code', 'res.total_questions', 'res.correct_answers', 'res.incorrect_answers', 'res.accuracy_rate', 'res.score', 'i.item_name', 'sr.name as routine_name')
            ->selectRaw("TRIM(COALESCE(s.last_name,'') || ' ' || COALESCE(s.first_name,'')) as student_name");
        $this->applyHistoryCommonFilters($query, $request, 'sr', 's', 'sc', 'g', 'rp', 'i', null, 'ss');
        if ($request->filled('completion_status')) {
            $query->where('ss.is_completed', $request->string('completion_status')->toString() === 'completed');
        }
        if ($request->filled('accuracy_min')) {
            $query->where('res.accuracy_rate', '>=', $request->float('accuracy_min'));
        }
        if ($request->filled('accuracy_max')) {
            $query->where('res.accuracy_rate', '<=', $request->float('accuracy_max'));
        }
        if ($request->filled('score_min')) {
            $query->where('res.score', '>=', $request->integer('score_min'));
        }
        if ($request->filled('score_max')) {
            $query->where('res.score', '<=', $request->integer('score_max'));
        }
        return $query->orderByDesc('ss.started_at')->paginate($perPage)->withQueryString();
    }

    private function applyHistoryCommonFilters($query, Request $request, string $routineAlias, string $studentAlias, string $schoolAlias, string $gradeAlias, string $packageAlias, string $itemAlias, ?string $dailyAlias = null, ?string $sessionAlias = null): void
    {
        if ($keyword = trim($request->string('keyword')->toString())) {
            $query->where(function ($where) use ($keyword, $routineAlias, $studentAlias, $packageAlias, $itemAlias): void {
                $where->where("{$studentAlias}.student_code", 'like', "%{$keyword}%")
                    ->orWhere("{$studentAlias}.last_name", 'like', "%{$keyword}%")
                    ->orWhere("{$studentAlias}.first_name", 'like', "%{$keyword}%")
                    ->orWhere("{$routineAlias}.name", 'like', "%{$keyword}%")
                    ->orWhere("{$packageAlias}.package_code", 'like', "%{$keyword}%")
                    ->orWhere("{$itemAlias}.item_name", 'like', "%{$keyword}%");
            });
        }
        if ($request->filled('school_id')) {
            $query->where("{$studentAlias}.school_id", $request->integer('school_id'));
        }
        if ($request->filled('grade_id')) {
            $query->where("{$studentAlias}.grade_id", $request->integer('grade_id'));
        }
        $dateAlias = $dailyAlias ? "{$dailyAlias}.target_date" : ($sessionAlias ? DB::raw("DATE({$sessionAlias}.started_at)") : "{$routineAlias}.start_date");
        if ($request->filled('from')) {
            $query->whereDate($dateAlias, '>=', $request->date('from')->toDateString());
        }
        if ($request->filled('to')) {
            $query->whereDate($dateAlias, '<=', $request->date('to')->toDateString());
        }
        if ($dailyAlias && $request->filled('status')) {
            $query->where("{$dailyAlias}.status", $request->string('status')->toString());
        }
    }

    private function attentionReasons(object $row): array
    {
        $reasons = [];
        if ((int) $row->unstarted_count > 0) {
            $reasons[] = '未着手'.$row->unstarted_count.'件';
        }
        if ((int) ($row->approval_pending ?? 0) > 0) {
            $reasons[] = '承認待ち'.$row->approval_pending.'件';
        }
        if ($row->accuracy_rate !== null && (float) $row->accuracy_rate < self::LOW_ACCURACY_RATE) {
            $reasons[] = '正答率が基準未満';
        }
        return $reasons;
    }

    private function perPage(Request $request, string $key, int $default): int
    {
        $value = $request->integer($key, $default);
        return in_array($value, [20, 25, 50, 100], true) ? $value : $default;
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds.'秒';
        }
        return intdiv($seconds, 60).'分'.($seconds % 60 ? ($seconds % 60).'秒' : '');
    }

    /**
     * 選択肢マスタをDB実定義に合わせて取得する。
     * name固定にせず、既存環境で利用されている表示名カラムを安全に判定する。
     */
    private function options(string $table): Collection
    {
        if (!Schema::hasTable($table)) return collect();

        $columns = Schema::getColumnListing($table);
        $labelColumn = collect(['name','label','display_name','type_name','completion_name','code'])
            ->first(fn(string $column) => in_array($column, $columns, true));

        if (!$labelColumn) {
            return DB::table($table)->select('id')->orderBy('id')->get()
                ->map(function(object $row) { $row->name = 'ID: '.$row->id; return $row; });
        }

        return DB::table($table)
            ->select('id', DB::raw($labelColumn.' as name'))
            ->orderBy($labelColumn)
            ->get();
    }

    /**
     * routine_completion_types の表示名カラムは環境差があるため、
     * 実カラムを確認してSELECT句を組み立てる。該当列が無い場合はID表示に退避する。
     */
    private function completionTypeSelect(string $alias): string
    {
        if (!Schema::hasTable('routine_completion_types')) {
            return 'NULL as completion_type_name';
        }

        $columns = Schema::getColumnListing('routine_completion_types');
        $labelColumn = collect(['name','label','display_name','type_name','completion_name','code'])
            ->first(fn(string $column) => in_array($column, $columns, true));

        return $labelColumn
            ? $alias.'.'.$labelColumn.' as completion_type_name'
            : "CAST({$alias}.id AS TEXT) as completion_type_name";
    }
    /**
     * 現在の割当済ルーティン状況と同じ検索条件を反映し、
     * student_routine_items単位でCSVを出力する。
     */
    public function exportHistoryCsv(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                '生徒', '生徒ID', '教室', '学年', '所属ルーティン', 'ルーティンコード',
                'ルーティンアイテム', '元アイテム', '元アイテムコード', '必須区分',
                '開始日', '完了日時', '状態', '達成日数', '必要日数', '進捗率',
                '学習時間', '平均正答率', '最終実施日時',
            ]);

            $page = 1;
            do {
                $pageRequest = clone $request;
                $pageRequest->merge(['per_page' => 100, 'page' => $page]);
                $rows = $this->itemHistory($pageRequest, 100);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->student_name,
                        $row->student_code,
                        $row->school_name,
                        $row->grade_name,
                        $row->routine_name,
                        $row->package_code,
                        $row->item_name,
                        $row->master_item_name,
                        $row->content_code,
                        $row->is_required ? '必須' : '任意',
                        $row->effective_start_date,
                        $row->completed_at,
                        $row->display_status,
                        $row->achieved_days,
                        $row->required_days,
                        $row->progress_rate,
                        $row->study_time_label,
                        $row->average_accuracy_rate,
                        $row->last_activity_at,
                    ]);
                }
                $page++;
            } while ($rows->hasMorePages());
            fclose($out);
        }, 'assigned-routine-status-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

}
