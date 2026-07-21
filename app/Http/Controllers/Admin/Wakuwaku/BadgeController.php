<?php

namespace App\Http\Controllers\Admin\Wakuwaku;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\BadgeCategory;
use App\Models\BadgeRequirement;
use App\Models\BadgeRequirementType;
use App\Models\BadgeSeries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BadgeController extends Controller
{
    public function index()
    {
        return view('admin.wakuwaku.badges.index', [
            'categories' => BadgeCategory::where('is_active', true)
                ->orderBy('display_order')
                ->get(),

            'series' => BadgeSeries::where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),

            'requirementTypes' => BadgeRequirementType::where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function list(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $categoryId = $request->query('category_id', 'all');
        $seriesId = $request->query('series_id', 'all');
        $level = $request->query('level', 'all');
        $status = $request->query('status', 'all');
        $grantMethod = $request->query('grant_method', 'all');
        $limited = $request->query('limited', 'all');
        $sort = $request->query('sort', 'display_order');
        $requirementStatus = $request->query('requirement_status', 'all');
        $usageStatus = $request->query('usage_status', 'all');
        $pointMin = $request->query('point_min');
        $pointMax = $request->query('point_max');

        $query = Badge::with(['category', 'series', 'requirements.requirementType'])
            ->withCount([
                'studentBadges as cumulative_grant_count',
                'studentBadges as active_holder_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->when($sort === 'updated_desc', fn ($q) => $q->orderByDesc('updated_at'))
            ->when($sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($sort === 'name_asc', fn ($q) => $q->orderBy('name')->orderBy('id'))
            ->when($sort === 'name_desc', fn ($q) => $q->orderByDesc('name')->orderBy('id'))
            // カテゴリ・シリーズはIDではなく、画面に表示する名称で並び替える。
            ->when($sort === 'category_asc', fn ($q) => $q
                ->orderByRaw('(SELECT bc.name FROM badge_categories bc WHERE bc.id = badges.badge_category_id) ASC NULLS LAST')
                ->orderBy('name')
                ->orderBy('id'))
            ->when($sort === 'category_desc', fn ($q) => $q
                ->orderByRaw('(SELECT bc.name FROM badge_categories bc WHERE bc.id = badges.badge_category_id) DESC NULLS LAST')
                ->orderBy('name')
                ->orderBy('id'))
            ->when($sort === 'series_asc', fn ($q) => $q
                ->orderByRaw('(SELECT bs.name FROM badge_series bs WHERE bs.id = badges.badge_series_id) ASC NULLS LAST')
                ->orderBy('name')
                ->orderBy('id'))
            ->when($sort === 'series_desc', fn ($q) => $q
                ->orderByRaw('(SELECT bs.name FROM badge_series bs WHERE bs.id = badges.badge_series_id) DESC NULLS LAST')
                ->orderBy('name')
                ->orderBy('id'))
            ->when($sort === 'level_asc', fn ($q) => $q->orderBy('level')->orderBy('display_order'))
            ->when($sort === 'level_desc', fn ($q) => $q->orderByDesc('level')->orderBy('display_order'))
            ->when($sort === 'points_asc', fn ($q) => $q->orderBy('point_reward')->orderBy('display_order'))
            ->when($sort === 'points_desc', fn ($q) => $q->orderByDesc('point_reward')->orderBy('display_order'))
            ->when($sort === 'holders_asc', fn ($q) => $q->orderBy('active_holder_count')->orderBy('name')->orderBy('id'))
            ->when($sort === 'holders_desc', fn ($q) => $q->orderByDesc('active_holder_count')->orderBy('name')->orderBy('id'))
            ->when($sort === 'grants_asc', fn ($q) => $q->orderBy('cumulative_grant_count')->orderBy('name')->orderBy('id'))
            ->when($sort === 'grants_desc', fn ($q) => $q->orderByDesc('cumulative_grant_count')->orderBy('name')->orderBy('id'))
            ->when($sort === 'status_asc', fn ($q) => $q->orderBy('is_active')->orderBy('name')->orderBy('id'))
            ->when($sort === 'status_desc', fn ($q) => $q->orderByDesc('is_active')->orderBy('name')->orderBy('id'))
            ->when(!in_array($sort, ['updated_desc', 'id_asc', 'id_desc', 'name_asc', 'name_desc', 'category_asc', 'category_desc', 'series_asc', 'series_desc', 'level_asc', 'level_desc', 'points_asc', 'points_desc', 'holders_asc', 'holders_desc', 'grants_asc', 'grants_desc', 'status_asc', 'status_desc'], true), fn ($q) => $q->orderBy('display_order')->orderBy('id'));

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($categoryId !== 'all') {
            $query->where('badge_category_id', $categoryId);
        }

        if ($seriesId !== 'all') {
            $query->where('badge_series_id', $seriesId);
        }

        if ($level !== 'all') {
            $query->where('level', $level);
        }

        if ($grantMethod !== 'all') {
            $query->where('grant_method', $grantMethod);
        }

        if ($limited === 'limited') {
            $query->where('is_limited', true);
        }

        if ($limited === 'normal') {
            $query->where('is_limited', false);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($requirementStatus === 'configured') {
            $query->whereHas('requirements');
        }

        if ($requirementStatus === 'unconfigured') {
            $query->whereDoesntHave('requirements');
        }

        if ($usageStatus === 'used') {
            $query->whereHas('studentBadges');
        }

        if ($usageStatus === 'unused') {
            $query->whereDoesntHave('studentBadges');
        }

        if (is_numeric($pointMin)) {
            $query->where('point_reward', '>=', (int) $pointMin);
        }

        if (is_numeric($pointMax)) {
            $query->where('point_reward', '<=', (int) $pointMax);
        }

        $rows = $query->get();

        if ($request->boolean('export')) {
            return $this->exportCsv($rows);
        }

        return response()->json([
            'rows' => $rows->map(function (Badge $badge) {
                $requirements = $badge->requirements->map(function (BadgeRequirement $requirement) {
                    return [
                        'id' => $requirement->id,
                        'badge_requirement_type_id' => $requirement->badge_requirement_type_id,
                        'requirement_type' => $requirement->requirement_type,
                        'requirement_type_name' => $requirement->requirementType?->name ?? $requirement->requirement_type,
                        'requirement_value' => $requirement->requirement_value,
                    ];
                })->values();

                $firstRequirement = $requirements->first();

                return [
                    'id' => $badge->id,
                    'category_id' => $badge->badge_category_id,
                    'category_name' => $badge->category?->name,
                    'series_id' => $badge->badge_series_id,
                    'series_name' => $badge->series?->name,
                    'code' => $badge->code,
                    'name' => $badge->name,
                    'level' => $badge->level,
                    'description' => $badge->description,
                    'image_path' => $this->resolveBadgeImageUrl($badge->image_path),
                    'requirements' => $requirements,
                    'requirement_type' => $firstRequirement['requirement_type'] ?? null,
                    'requirement_type_name' => $firstRequirement['requirement_type_name'] ?? null,
                    'requirement_value' => $firstRequirement['requirement_value'] ?? null,
                    'point_reward' => $badge->point_reward,
                    'active_holder_count' => $badge->active_holder_count ?? 0,
                    'cumulative_grant_count' => $badge->cumulative_grant_count ?? 0,
                    'grant_method' => $badge->grant_method?->value ?? $badge->grant_method,
                    'acquisition_message' => $badge->acquisition_message,
                    'allow_regrant' => (bool) $badge->allow_regrant,
                    'notify_on_grant' => (bool) $badge->notify_on_grant,
                    'is_limited' => (bool) $badge->is_limited,
                    'start_date' => $badge->start_date,
                    'end_date' => $badge->end_date,
                    'display_order' => $badge->display_order,
                    'is_active' => (bool) $badge->is_active,
                    'condition_operator' => $badge->condition_operator ?? 'and',
                ];
            }),
            'summary' => [
                'total' => Badge::count(),
                'current_holdings' => DB::table('student_badges')->where('status', 'active')->count(),
                'monthly_grants' => DB::table('student_badges')
                    ->whereBetween('acquired_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'monthly_removals' => DB::table('student_badges')
                    ->whereNotNull('removed_at')
                    ->whereBetween('removed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'previous_month_grants' => DB::table('student_badges')
                    ->whereBetween('acquired_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
                    ->count(),
                'previous_month_removals' => DB::table('student_badges')
                    ->whereNotNull('removed_at')
                    ->whereBetween('removed_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'badge_category_id' => ['required', 'exists:badge_categories,id'],
            'badge_series_id' => ['nullable', 'exists:badge_series,id'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:99'],
            'description' => ['nullable', 'string'],
            'acquisition_message' => ['nullable', 'string'],
            'grant_method' => ['required', Rule::in(['auto', 'manual', 'both'])],
            'allow_regrant' => ['boolean'],
            'notify_on_grant' => ['boolean'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'condition_operator' => ['required', Rule::in(['and', 'or'])],
            'requirements' => ['nullable', 'array', 'max:3'],
            'requirements.*.badge_requirement_type_id' => ['required_with:requirements', 'exists:badge_requirement_types,id'],
            'requirements.*.requirement_value' => ['nullable', 'string', 'max:255'],
            'point_reward' => ['nullable', 'integer', 'min:0'],
            'is_limited' => ['boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $badge = Badge::create([
                'badge_category_id' => $validated['badge_category_id'],
                'badge_series_id' => $validated['badge_series_id'] ?? null,
                'code' => $this->generateBadgeCode(),
                'name' => $validated['name'],
                'level' => $validated['level'],
                'description' => $validated['description'] ?? null,
                'acquisition_message' => $validated['acquisition_message'] ?? null,
                'grant_method' => $validated['grant_method'],
                'allow_regrant' => (bool) ($validated['allow_regrant'] ?? false),
                'notify_on_grant' => (bool) ($validated['notify_on_grant'] ?? false),
                'condition_operator' => $validated['condition_operator'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'image_path' => null,
                'point_reward' => $validated['point_reward'] ?? 0,
                'is_limited' => (bool) ($validated['is_limited'] ?? false),
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'display_order' => (Badge::max('display_order') ?? 0) + 1,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            if (!empty($validated['image_file'])) {
                $path = $validated['image_file']->storeAs(
                    'badges/' . $badge->id,
                    'badge.png',
                    's3'
                );

                $badge->update([
                    'image_path' => $path,
                ]);
            }

            $this->syncRequirements($badge, $validated['requirements'] ?? []);
        });

        return response()->json(['message' => '保存しました。']);
    }

    public function update(Request $request, Badge $badge)
    {
        $validated = $request->validate([
            'badge_category_id' => ['required', 'exists:badge_categories,id'],
            'badge_series_id' => ['nullable', 'exists:badge_series,id'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:99'],
            'description' => ['nullable', 'string'],
            'acquisition_message' => ['nullable', 'string'],
            'grant_method' => ['required', Rule::in(['auto', 'manual', 'both'])],
            'allow_regrant' => ['boolean'],
            'notify_on_grant' => ['boolean'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'condition_operator' => ['required', Rule::in(['and', 'or'])],
            'requirements' => ['nullable', 'array', 'max:3'],
            'requirements.*.badge_requirement_type_id' => ['required_with:requirements', 'exists:badge_requirement_types,id'],
            'requirements.*.requirement_value' => ['nullable', 'string', 'max:255'],
            'point_reward' => ['nullable', 'integer', 'min:0'],
            'is_limited' => ['boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated, $badge) {
            $badge->update([
                'badge_category_id' => $validated['badge_category_id'],
                'badge_series_id' => $validated['badge_series_id'] ?? null,
                'name' => $validated['name'],
                'level' => $validated['level'],
                'description' => $validated['description'] ?? null,
                'acquisition_message' => $validated['acquisition_message'] ?? null,
                'grant_method' => $validated['grant_method'],
                'allow_regrant' => (bool) ($validated['allow_regrant'] ?? false),
                'notify_on_grant' => (bool) ($validated['notify_on_grant'] ?? false),
                'condition_operator' => $validated['condition_operator'],
                'updated_by' => auth()->id(),
                'point_reward' => $validated['point_reward'] ?? 0,
                'is_limited' => (bool) ($validated['is_limited'] ?? false),
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            if (!empty($validated['image_file'])) {
                $path = $validated['image_file']->storeAs(
                    'badges/' . $badge->id,
                    'badge.png',
                    's3'
                );

                $badge->update([
                    'image_path' => $path,
                ]);
            }

            $this->syncRequirements($badge, $validated['requirements'] ?? []);
        });

        return response()->json(['message' => '更新しました。']);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:badges,id'],
            'items.*.display_order' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                Badge::whereKey($item['id'])->update([
                    'display_order' => $item['display_order'],
                ]);
            }
        });

        return response()->json(['message' => '並び順を更新しました。']);
    }

    public function duplicate(Badge $badge)
    {
        DB::transaction(function () use ($badge) {
            $badge->load('requirements');

            $newBadge = $badge->replicate();
            $newBadge->code = $this->generateBadgeCode();
            $newBadge->name = $badge->name . '（コピー）';
            $newBadge->display_order = (Badge::max('display_order') ?? 0) + 1;
            $newBadge->image_path = null;
            $newBadge->save();

            if ($badge->image_path) {
                $newImagePath = 'badges/' . $newBadge->id . '/badge.png';

                if (Storage::disk('s3')->exists($badge->image_path)) {
                    Storage::disk('s3')->copy($badge->image_path, $newImagePath);

                    $newBadge->update([
                        'image_path' => $newImagePath,
                    ]);
                }
            }

            foreach ($badge->requirements as $requirement) {
                $newRequirement = $requirement->replicate();
                $newRequirement->badge_id = $newBadge->id;
                $newRequirement->save();
            }
        });

        return response()->json([
            'message' => '複製しました。',
        ]);
    }

    public function toggleActive(Badge $badge)
    {
        $badge->update([
            'is_active' => !$badge->is_active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['message' => $badge->is_active ? '有効化しました。' : '無効化しました。']);
    }

    public function destroy(Badge $badge)
    {
        if ($badge->studentBadges()->exists()) {
            return response()->json([
                'message' => '生徒に付与済みのため削除できません。',
            ], 422);
        }

        Storage::disk('s3')->deleteDirectory(
            'badges/' . $badge->id
        );

        $badge->requirements()->delete();
        $badge->delete();

        return response()->json([
            'message' => '削除しました。',
        ]);
    }


    public function bulkActivate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:badges,id'],
        ]);

        Badge::whereIn('id', $validated['ids'])->update([
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => '一括有効化しました。',
        ]);
    }

    public function bulkDeactivate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:badges,id'],
        ]);

        Badge::whereIn('id', $validated['ids'])->update([
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => '一括無効化しました。',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:badges,id'],
        ]);

        $badges = Badge::with(['studentBadges'])
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($badges as $badge) {
            if ($badge->studentBadges->isNotEmpty()) {
                return response()->json([
                    'message' => '生徒に付与済みのバッジがあるため削除できません。',
                ], 422);
            }
        }

        DB::transaction(function () use ($badges) {
            foreach ($badges as $badge) {
                Storage::disk('s3')->deleteDirectory(
                    'badges/' . $badge->id
                );

                $badge->requirements()->delete();
                $badge->delete();
            }
        });

        return response()->json([
            'message' => '一括削除しました。',
        ]);
    }

    private function syncRequirements(Badge $badge, array $requirements): void
    {
        $badge->requirements()->delete();

        foreach (array_slice($requirements, 0, 3) as $item) {
            if (empty($item['badge_requirement_type_id'])) {
                continue;
            }

            $type = BadgeRequirementType::find($item['badge_requirement_type_id']);

            BadgeRequirement::create([
                'badge_id' => $badge->id,
                'badge_requirement_type_id' => $item['badge_requirement_type_id'],
                'requirement_type' => $type?->code ?? '',
                'requirement_value' => $item['requirement_value'] ?? '',
            ]);
        }
    }

    private function resolveBadgeImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (Storage::disk('s3')->exists($path)) {
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return null;
    }
    /**
     * バッジコードを LF-BDG-001 形式で自動採番する。
     */
    private function exportCsv($rows)
    {
        $filename = 'badge-list-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'コード', 'バッジ名', 'カテゴリ', 'シリーズ', 'レベル',
                '付与方法', '一般/限定', '獲得条件', '獲得ポイント',
                '現在保有者数', '累計獲得数', '状態', '開始日', '終了日', '説明',
            ]);

            foreach ($rows as $badge) {
                $requirements = $badge->requirements->map(function ($requirement) {
                    $name = $requirement->requirementType?->name ?? $requirement->requirement_type ?? '条件';
                    return $requirement->requirement_value
                        ? $name . '：' . $requirement->requirement_value
                        : $name;
                })->implode($badge->condition_operator === 'or' ? ' OR ' : ' AND ');

                fputcsv($handle, [
                    $badge->id,
                    $badge->code,
                    $badge->name,
                    $badge->category?->name,
                    $badge->series?->name,
                    'Lv' . $badge->level,
                    match ($badge->grant_method?->value ?? $badge->grant_method) {
                        'auto' => '自動',
                        'manual' => '手動',
                        default => '自動・手動',
                    },
                    $badge->is_limited ? '限定' : '一般',
                    $requirements,
                    $badge->point_reward,
                    $badge->active_holder_count ?? 0,
                    $badge->cumulative_grant_count ?? 0,
                    $badge->is_active ? '有効' : '無効',
                    $badge->start_date,
                    $badge->end_date,
                    $badge->description,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function generateBadgeCode(): string
    {
        $numbers = Badge::query()
            ->lockForUpdate()
            ->where('code', 'like', 'LF-BDG-%')
            ->pluck('code')
            ->map(function (?string $code): int {
                return preg_match('/^LF-BDG-(\d+)$/', (string) $code, $matches)
                    ? (int) $matches[1]
                    : 0;
            });

        return 'LF-BDG-' . str_pad((string) (($numbers->max() ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }

}