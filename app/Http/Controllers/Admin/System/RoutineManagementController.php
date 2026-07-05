<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoutineManagementController extends Controller
{
    private array $learningStatusMap = [
        'not_created' => '未作成',
        'uncreated' => '未作成',
        'not-started' => '未作成',
        'draft' => '作成中',
        'drafting' => '作成中',
        'in_progress' => '作成中',
        'creating' => '作成中',
        'published' => '作成済',
        'done' => '作成済',
        'completed' => '作成済',
        'created' => '作成済',
        'none' => '不要',
        'unnecessary' => '不要',
        'not_required' => '不要',
        '不要' => '不要',
        '未作成' => '未作成',
        '作成中' => '作成中',
        '作成済' => '作成済',
    ];

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'items') === 'routines' ? 'routines' : 'items';

        return view('admin.system.routines.index', [
            'tab' => $tab,
            'items' => $tab === 'items' ? $this->routineItems($request) : collect(),
            'routines' => $tab === 'routines' ? $this->routines($request) : collect(),
            'gradeOptions' => $this->gradeOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'difficultyOptions' => [1, 2, 3, 4, 5],
            'learningPageStatuses' => ['未作成', '作成中', '作成済', '不要'],
        ]);
    }

    public function updateItem(Request $request, int $routineContentId)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_grade' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'estimated_days' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'daily_learning_minutes' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'search_tags' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $payload = $this->onlyExistingColumns('routine_contents', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_grade' => $data['target_grade'] ?? null,
            'target_level' => $data['target_grade'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'estimated_days' => $data['estimated_days'] ?? null,
            'daily_learning_minutes' => $data['daily_learning_minutes'] ?? null,
            'search_tags' => $data['search_tags'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        DB::table('routine_contents')->where('id', $routineContentId)->update($payload);

        return response()->json(['message' => '保存しました。']);
    }

    public function updateRoutine(Request $request, int $routinePackageId)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_grade' => ['nullable', 'string', 'max:255'],
            'target_level' => ['nullable', 'string', 'max:255'],
            'search_tags' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $payload = $this->onlyExistingColumns('routine_packages', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_grade' => $data['target_grade'] ?? null,
            'target_level' => $data['target_level'] ?? null,
            'search_tags' => $data['search_tags'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        DB::table('routine_packages')->where('id', $routinePackageId)->update($payload);

        return response()->json(['message' => '保存しました。']);
    }

    public function duplicateItem(int $routineContentId)
    {
        DB::transaction(function () use ($routineContentId) {
            $source = (array) DB::table('routine_contents')->where('id', $routineContentId)->first();
            abort_if(empty($source), 404);

            unset($source['id']);
            $source['name'] = trim(($source['name'] ?? 'ルーティンアイテム') . ' コピー');
            if (array_key_exists('content_code', $source)) {
                $source['content_code'] = $this->nextCode('routine_contents', 'content_code', 'CONT-');
            }
            if (array_key_exists('created_by', $source)) {
                $source['created_by'] = Auth::id();
            }
            if (array_key_exists('updated_by', $source)) {
                $source['updated_by'] = Auth::id();
            }
            if (array_key_exists('created_at', $source)) {
                $source['created_at'] = now();
            }
            if (array_key_exists('updated_at', $source)) {
                $source['updated_at'] = now();
            }

            DB::table('routine_contents')->insert($source);
        });

        return back()->with('status', 'ルーティンアイテムを複製しました。');
    }

    public function duplicateRoutine(int $routinePackageId)
    {
        DB::transaction(function () use ($routinePackageId) {
            $source = (array) DB::table('routine_packages')->where('id', $routinePackageId)->first();
            abort_if(empty($source), 404);

            $oldId = $source['id'];
            unset($source['id']);
            $source['name'] = trim(($source['name'] ?? 'ルーティン') . ' コピー');
            if (array_key_exists('package_code', $source)) {
                $source['package_code'] = $this->nextCode('routine_packages', 'package_code', 'PKG-');
            }
            if (array_key_exists('created_by', $source)) {
                $source['created_by'] = Auth::id();
            }
            if (array_key_exists('updated_by', $source)) {
                $source['updated_by'] = Auth::id();
            }
            if (array_key_exists('created_at', $source)) {
                $source['created_at'] = now();
            }
            if (array_key_exists('updated_at', $source)) {
                $source['updated_at'] = now();
            }

            $newId = DB::table('routine_packages')->insertGetId($source);

            if (Schema::hasTable('routine_package_items')) {
                DB::table('routine_package_items')
                    ->where('routine_package_id', $oldId)
                    ->orderBy('order_no')
                    ->get()
                    ->each(function ($item) use ($newId) {
                        $copy = (array) $item;
                        unset($copy['id']);
                        $copy['routine_package_id'] = $newId;
                        if (array_key_exists('created_at', $copy)) {
                            $copy['created_at'] = now();
                        }
                        if (array_key_exists('updated_at', $copy)) {
                            $copy['updated_at'] = now();
                        }
                        DB::table('routine_package_items')->insert($copy);
                    });
            }
        });

        return back()->with('status', 'ルーティンを複製しました。');
    }

    private function routineItems(Request $request)
    {
        $userId = Auth::id() ?? 1;
        $hasMarks = Schema::hasTable('user_routine_item_marks');
        $hasLearningPages = Schema::hasTable('learning_pages');
        $hasCreator = Schema::hasTable('users') && Schema::hasColumn('routine_contents', 'created_by');
        $hasUpdater = Schema::hasTable('users') && Schema::hasColumn('routine_contents', 'updated_by');

        $query = DB::table('routine_contents as rc');

        if ($hasMarks) {
            $query->leftJoin('user_routine_item_marks as marks', function ($join) use ($userId) {
                $join->on('marks.routine_content_id', '=', 'rc.id')->where('marks.user_id', '=', $userId);
            });
        }
        if ($hasLearningPages) {
            $query->leftJoin('learning_pages as lp', 'lp.routine_content_id', '=', 'rc.id');
        }
        if ($hasCreator) {
            $query->leftJoin('users as creator', 'creator.id', '=', 'rc.created_by');
        }
        if ($hasUpdater) {
            $query->leftJoin('users as updater', 'updater.id', '=', 'rc.updated_by');
        }

        $query->select('rc.*')
            ->addSelect([
                'is_favorite' => $hasMarks ? DB::raw('COALESCE(marks.is_favorite, false)') : DB::raw('false'),
                'is_frequently_used' => $hasMarks ? DB::raw('COALESCE(marks.is_frequently_used, false)') : DB::raw('false'),
                'created_by_name' => $hasCreator ? DB::raw('creator.name') : DB::raw('NULL'),
                'updated_by_name' => $hasUpdater ? DB::raw('updater.name') : DB::raw('NULL'),
                'learning_page_status_label' => $this->learningStatusSelectSql($hasLearningPages),
            ]);

        if (Schema::hasTable('routine_package_items')) {
            $query->selectSub(function ($q) {
                $q->from('routine_package_items')->selectRaw('COUNT(*)')->whereColumn('routine_package_items.routine_content_id', 'rc.id');
            }, 'used_routine_count')
            ->selectSub(function ($q) {
                $q->from('routine_package_items')
                    ->selectRaw("MIN(NULLIF(item_name, ''))")
                    ->whereColumn('routine_package_items.routine_content_id', 'rc.id');
            }, 'package_item_name')
            ->selectSub(function ($q) {
                $q->from('routine_package_items')
                    ->selectRaw("STRING_AGG(DISTINCT NULLIF(target_grade, ''), '、')")
                    ->whereColumn('routine_package_items.routine_content_id', 'rc.id');
            }, 'package_item_target_grades');
        } else {
            $query->addSelect([
                'used_routine_count' => DB::raw('0'),
                'package_item_name' => DB::raw('NULL'),
                'package_item_target_grades' => DB::raw('NULL'),
            ]);
        }

        if (Schema::hasTable('student_routine_items') && Schema::hasTable('student_routines')) {
            $query->selectSub(function ($q) {
                $q->from('student_routine_items')->selectRaw('COUNT(DISTINCT student_routines.student_id)')
                    ->join('student_routines', 'student_routines.id', '=', 'student_routine_items.student_routine_id')
                    ->whereColumn('student_routine_items.routine_content_id', 'rc.id');
            }, 'assigned_student_count');
        } else {
            $query->addSelect(['assigned_student_count' => DB::raw('0')]);
        }

        if (Schema::hasTable('routine_study_sessions')) {
            $query->selectSub(function ($q) {
                $q->from('routine_study_sessions')->selectRaw('COUNT(*)')->whereColumn('routine_study_sessions.routine_content_id', 'rc.id');
            }, 'past_study_count')
            ->selectSub(function ($q) {
                $q->from('routine_study_sessions')->selectRaw('MAX(ended_at)')->whereColumn('routine_study_sessions.routine_content_id', 'rc.id');
            }, 'last_studied_at');
        } else {
            $query->addSelect(['past_study_count' => DB::raw('0'), 'last_studied_at' => DB::raw('NULL')]);
        }

        $keyword = trim((string) $request->query('item_keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                if (is_numeric($keyword)) {
                    $q->orWhere('rc.id', (int) $keyword);
                }
                $q->orWhere('rc.name', 'like', "%{$keyword}%")
                    ->orWhere('rc.description', 'like', "%{$keyword}%");
            });
        }

        $this->applyColumnFilter($query, 'rc', 'category_name', $request->query('item_category'));
        $this->applyGradeFilter($query, 'rc', $request->query('item_grade'));
        $this->applyColumnFilter($query, 'rc', 'difficulty', $request->query('item_difficulty'));

        $learningStatus = $request->query('item_learning_status');
        if ($learningStatus && $learningStatus !== 'all') {
            $rawStatuses = array_keys(array_filter($this->learningStatusMap, fn ($label) => $label === $learningStatus));
            $query->where(function ($q) use ($learningStatus, $rawStatuses, $hasLearningPages) {
                if (Schema::hasColumn('routine_contents', 'learning_page_status')) {
                    $q->orWhereIn('rc.learning_page_status', array_merge([$learningStatus], $rawStatuses));
                }
                if ($hasLearningPages && Schema::hasColumn('learning_pages', 'status')) {
                    $q->orWhereIn('lp.status', array_merge([$learningStatus], $rawStatuses));
                }
            });
        }

        if ($hasMarks && $request->query('item_favorite') === 'yes') {
            $query->where('marks.is_favorite', true);
        }
        if ($hasMarks && $request->query('item_frequent') === 'yes') {
            $query->where('marks.is_frequently_used', true);
        }
        if ($request->query('item_active') === 'active') {
            $query->where('rc.is_active', true);
        } elseif ($request->query('item_active') === 'inactive') {
            $query->where('rc.is_active', false);
        }

        $items = $query->orderByRaw($this->orderColumnSql('rc', 'sort_order'))
            ->orderBy('rc.id')
            ->paginate(20)
            ->withQueryString();

        $contentIds = $items->pluck('id')->all();
        $usedRoutineNames = $this->usedRoutineNamesByContentIds($contentIds);
        $items->getCollection()->transform(function ($item) use ($usedRoutineNames) {
            $item->display_name = $this->fallbackName($item->name ?? null, $item->package_item_name ?? null, '名称未設定');
            $item->display_grade = $this->fallbackName($item->target_grade ?? null, $item->package_item_target_grades ?? null, '-');
            $item->learning_page_status_label = $this->normalizeLearningStatus($item->learning_page_status_label ?? $item->learning_page_status ?? null);
            $item->used_routine_names = $usedRoutineNames[$item->id] ?? [];
            $item->search_tags = $item->search_tags ?? '';
            return $item;
        });

        return $items;
    }

    private function routines(Request $request)
    {
        $itemSummary = Schema::hasTable('routine_package_items')
            ? DB::table('routine_package_items')
                ->select('routine_package_id')
                ->selectRaw('COUNT(*) as item_count')
                ->selectRaw('COALESCE(SUM(COALESCE(required_days, 0) * COALESCE(estimated_minutes, 0)), 0) as total_learning_minutes')
                ->groupBy('routine_package_id')
            : null;

        $studentSummary = Schema::hasTable('student_routines')
            ? DB::table('student_routines')
                ->select('routine_package_id')
                ->selectRaw('COUNT(*) as student_routine_count')
                ->selectRaw('COUNT(DISTINCT student_id) as assigned_student_count')
                ->groupBy('routine_package_id')
            : null;

        $hasPackageCreator = Schema::hasTable('users') && Schema::hasColumn('routine_packages', 'created_by');
        $hasPackageUpdater = Schema::hasTable('users') && Schema::hasColumn('routine_packages', 'updated_by');

        $query = DB::table('routine_packages as rp');
        if ($itemSummary) {
            $query->leftJoinSub($itemSummary, 'item_summary', fn ($join) => $join->on('item_summary.routine_package_id', '=', 'rp.id'));
        }
        if ($studentSummary) {
            $query->leftJoinSub($studentSummary, 'student_summary', fn ($join) => $join->on('student_summary.routine_package_id', '=', 'rp.id'));
        }
        if ($hasPackageCreator) {
            $query->leftJoin('users as creator', 'creator.id', '=', 'rp.created_by');
        }
        if ($hasPackageUpdater) {
            $query->leftJoin('users as updater', 'updater.id', '=', 'rp.updated_by');
        }

        $query->select('rp.*')->addSelect([
            'created_by_name' => $hasPackageCreator ? DB::raw('creator.name') : DB::raw('NULL'),
            'updated_by_name' => $hasPackageUpdater ? DB::raw('updater.name') : DB::raw('NULL'),
            'item_count' => $itemSummary ? DB::raw('COALESCE(item_summary.item_count, 0)') : DB::raw('0'),
            'total_learning_minutes' => $itemSummary ? DB::raw('COALESCE(item_summary.total_learning_minutes, 0)') : DB::raw('0'),
            'assigned_student_count' => $studentSummary ? DB::raw('COALESCE(student_summary.assigned_student_count, 0)') : DB::raw('0'),
            'student_routine_count' => $studentSummary ? DB::raw('COALESCE(student_summary.student_routine_count, 0)') : DB::raw('0'),
        ]);

        $keyword = trim((string) $request->query('routine_keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                if (is_numeric($keyword)) {
                    $q->orWhere('rp.id', (int) $keyword);
                }
                $q->orWhere('rp.name', 'like', "%{$keyword}%")
                    ->orWhere('rp.description', 'like', "%{$keyword}%");
            });
        }

        $this->applyColumnFilter($query, 'rp', 'target_grade', $request->query('routine_grade'));
        $routineDifficulty = $request->query('routine_difficulty');
        if ($routineDifficulty && $routineDifficulty !== 'all' && Schema::hasColumn('routine_packages', 'target_level')) {
            $numericDifficulty = array_search($routineDifficulty, [1 => '非常に低い', 2 => '低い', 3 => '標準', 4 => '高い', 5 => '非常に高い'], true);
            $query->where(function ($q) use ($routineDifficulty, $numericDifficulty) {
                $q->where('rp.target_level', $routineDifficulty);
                if ($numericDifficulty !== false) {
                    $q->orWhere('rp.target_level', (string) $numericDifficulty);
                }
            });
        }
        if ($request->query('routine_active') === 'active') {
            $query->where('rp.is_active', true);
        } elseif ($request->query('routine_active') === 'inactive') {
            $query->where('rp.is_active', false);
        }
        if ($studentSummary) {
            if ($request->query('usage') === 'used') {
                $query->whereRaw('COALESCE(student_summary.student_routine_count, 0) > 0');
            } elseif ($request->query('usage') === 'unused') {
                $query->whereRaw('COALESCE(student_summary.student_routine_count, 0) = 0');
            }
            if (($min = $request->query('assigned_min')) !== null && $min !== '') {
                $query->whereRaw('COALESCE(student_summary.assigned_student_count, 0) >= ?', [(int) $min]);
            }
            if (($max = $request->query('assigned_max')) !== null && $max !== '') {
                $query->whereRaw('COALESCE(student_summary.assigned_student_count, 0) <= ?', [(int) $max]);
            }
        }

        $routines = $query->orderByRaw($this->orderColumnSql('rp', 'sort_order'))
            ->orderBy('rp.id')
            ->paginate(20)
            ->withQueryString();

        $ids = $routines->pluck('id')->all();
        $items = $this->routinePackageItemsByPackageIds($ids);
        $routines->getCollection()->transform(function ($routine) use ($items) {
            $routine->items = $items[$routine->id] ?? collect();
            $routine->display_name = $this->routinePackageDisplayName($routine);
            $routine->display_grade = $routine->target_grade ?? '-';
            $routine->display_level = $this->normalizeDifficultyLabel($routine->target_level ?? null);
            $routine->search_tags = $routine->search_tags ?? '';
            return $routine;
        });

        return $routines;
    }

    private function usedRoutineNamesByContentIds(array $contentIds): array
    {
        if (empty($contentIds) || ! Schema::hasTable('routine_package_items') || ! Schema::hasTable('routine_packages')) {
            return [];
        }

        return DB::table('routine_package_items as rpi')
            ->join('routine_packages as rp', 'rp.id', '=', 'rpi.routine_package_id')
            ->whereIn('rpi.routine_content_id', $contentIds)
            ->orderBy('rp.id')
            ->get(['rpi.routine_content_id', 'rp.name', 'rp.package_code'])
            ->groupBy('routine_content_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => $this->fallbackName($row->name, $row->package_code, '名称未設定'))->values()->all())
            ->all();
    }

    private function routinePackageItemsByPackageIds(array $ids)
    {
        if (empty($ids) || ! Schema::hasTable('routine_package_items')) {
            return collect();
        }

        $query = DB::table('routine_package_items as rpi');
        if (Schema::hasTable('routine_contents')) {
            $query->leftJoin('routine_contents as rc', 'rc.id', '=', 'rpi.routine_content_id');
        }

        return $query->whereIn('rpi.routine_package_id', $ids)
            ->orderBy('rpi.routine_package_id')
            ->orderBy('rpi.order_no')
            ->get([
                'rpi.routine_package_id',
                'rpi.item_name',
                'rpi.required_days',
                'rpi.estimated_minutes',
                DB::raw(Schema::hasTable('routine_contents') ? 'rc.name as content_name' : 'NULL as content_name'),
            ])
            ->groupBy('routine_package_id');
    }

    private function learningStatusSelectSql(bool $hasLearningPages)
    {
        if (Schema::hasColumn('routine_contents', 'learning_page_status') && $hasLearningPages && Schema::hasColumn('learning_pages', 'status')) {
            return DB::raw('COALESCE(rc.learning_page_status, lp.status)');
        }
        if (Schema::hasColumn('routine_contents', 'learning_page_status')) {
            return DB::raw('rc.learning_page_status');
        }
        if ($hasLearningPages && Schema::hasColumn('learning_pages', 'status')) {
            return DB::raw('lp.status');
        }
        return DB::raw("'未作成'");
    }

    private function normalizeLearningStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return '未作成';
        }
        return $this->learningStatusMap[$status] ?? $status;
    }

    private function normalizeDifficultyLabel(?string $value): string
    {
        if (in_array($value, ['非常に低い', '低い', '標準', '高い', '非常に高い'], true)) {
            return $value;
        }
        if (is_numeric($value)) {
            return [1 => '非常に低い', 2 => '低い', 3 => '標準', 4 => '高い', 5 => '非常に高い'][(int) $value] ?? '標準';
        }
        return '標準';
    }

    private function applyColumnFilter($query, string $alias, string $column, $value): void
    {
        if ($value === null || $value === '' || $value === 'all') {
            return;
        }
        $table = $alias === 'rc' ? 'routine_contents' : 'routine_packages';
        if (! Schema::hasColumn($table, $column)) {
            return;
        }
        $query->where("{$alias}.{$column}", $value);
    }

    private function applyGradeFilter($query, string $alias, $value): void
    {
        if ($value === null || $value === '' || $value === 'all') {
            return;
        }
        $query->where(function ($q) use ($alias, $value) {
            if (Schema::hasColumn('routine_contents', 'target_grade')) {
                $q->orWhere("{$alias}.target_grade", $value);
            }
            if (Schema::hasColumn('routine_contents', 'target_level')) {
                $q->orWhere("{$alias}.target_level", $value);
            }
        });
    }

    private function orderColumnSql(string $alias, string $column): string
    {
        $table = $alias === 'rc' ? 'routine_contents' : 'routine_packages';
        return Schema::hasColumn($table, $column) ? "{$alias}.{$column} ASC NULLS LAST" : "{$alias}.id ASC";
    }

    private function onlyExistingColumns(string $table, array $payload): array
    {
        return collect($payload)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
    }

    private function nextCode(string $table, string $column, string $prefix): string
    {
        $maxId = (int) DB::table($table)->max('id') + 1;
        return $prefix . str_pad((string) $maxId, 4, '0', STR_PAD_LEFT);
    }

    private function gradeOptions(): array
    {
        $values = collect();
        foreach ([['routine_contents', 'target_grade'], ['routine_contents', 'target_level'], ['routine_packages', 'target_grade']] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $values = $values->merge(DB::table($table)->whereNotNull($column)->where($column, '<>', '')->distinct()->pluck($column));
            }
        }
        return $values->unique()->sort()->values()->all();
    }

    private function categoryOptions(): array
    {
        if (! Schema::hasTable('routine_contents') || ! Schema::hasColumn('routine_contents', 'category_name')) {
            return [];
        }
        return DB::table('routine_contents')
            ->whereNotNull('category_name')
            ->where('category_name', '<>', '')
            ->distinct()
            ->orderBy('category_name')
            ->pluck('category_name')
            ->all();
    }


    private function routinePackageDisplayName(object $routine): string
    {
        $name = trim((string) ($routine->name ?? ''));
        $code = trim((string) ($routine->package_code ?? ''));

        if ($name !== '' && $name !== $code && ! $this->looksLikeRoutinePackageCode($name)) {
            return $name;
        }

        $knownNames = [
            'RP-BASIC-001' => '朝の脳活ルーティン（基礎）',
            'RP-THINK-001' => '思考力トレーニングルーティン',
        ];

        if ($code !== '' && isset($knownNames[$code])) {
            return $knownNames[$code];
        }

        if ($name !== '' && isset($knownNames[$name])) {
            return $knownNames[$name];
        }

        $sourceCode = $code !== '' ? $code : $name;
        if ($sourceCode !== '') {
            $label = $this->routinePackageCodeLabel($sourceCode);
            if ($label !== null) {
                return $label;
            }
        }

        return $this->fallbackName($name !== '' ? $name : null, $code !== '' ? $code : null, '名称未設定');
    }

    private function looksLikeRoutinePackageCode(string $value): bool
    {
        return (bool) preg_match('/^RP-[A-Z0-9_-]+$/i', trim($value));
    }

    private function routinePackageCodeLabel(string $code): ?string
    {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^RP-([A-Z0-9]+)-?/', $normalized, $matches)) {
            return null;
        }

        return match ($matches[1]) {
            'BASIC' => '基礎ルーティン',
            'THINK' => '思考力ルーティン',
            'MEM' => '記憶力ルーティン',
            'FOCUS' => '集中力ルーティン',
            'LOGIC' => '論理思考ルーティン',
            default => null,
        };
    }

    private function fallbackName(?string $name, ?string $code, string $fallback): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return $name;
        }
        $code = trim((string) $code);
        return $code !== '' ? $code : $fallback;
    }
}
