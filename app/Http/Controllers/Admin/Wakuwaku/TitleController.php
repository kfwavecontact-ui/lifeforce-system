<?php

namespace App\Http\Controllers\Admin\Wakuwaku;

use App\Http\Controllers\Controller;
use App\Models\Title;
use App\Models\TitleCategory;
use App\Models\TitleRequirement;
use App\Models\TitleRequirementType;
use App\Models\TitleSeries;
use App\Models\TitleTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TitleController extends Controller
{
    public function index()
    {
        return view('admin.wakuwaku.titles.index', [
            'categories' => TitleCategory::where('is_active', true)
                ->orderBy('display_order')
                ->get(),

            'series' => TitleSeries::where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),

            'requirementTypes' => TitleRequirementType::where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),

            'titleTags' => TitleTag::where('is_active', true)
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
        $tagKeyword = trim((string) $request->query('tag_keyword', ''));

        $query = Title::with(['category', 'series', 'requirements.requirementType', 'tags'])
            ->withCount([
                'studentTitles as cumulative_grant_count',
                'studentTitles as active_holder_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->when($sort === 'updated_desc', fn ($q) => $q->orderByDesc('updated_at'))
            ->when($sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($sort === 'name_asc', fn ($q) => $q->orderBy('name')->orderBy('id'))
            ->when($sort === 'name_desc', fn ($q) => $q->orderByDesc('name')->orderBy('id'))
            // カテゴリ・シリーズはIDではなく、画面に表示する名称で並び替える。
            ->when($sort === 'category_asc', fn ($q) => $q
                ->orderByRaw('(SELECT bc.name FROM title_categories bc WHERE bc.id = titles.title_category_id) ASC NULLS LAST')
                ->orderBy('name')
                ->orderBy('id'))
            ->when($sort === 'category_desc', fn ($q) => $q
                ->orderByRaw('(SELECT bc.name FROM title_categories bc WHERE bc.id = titles.title_category_id) DESC NULLS LAST')
                ->orderBy('name')
                ->orderBy('id'))
            ->when($sort === 'series_asc', fn ($q) => $q
                ->orderByRaw('(SELECT bs.name FROM title_series bs WHERE bs.id = titles.title_series_id) ASC NULLS LAST')
                ->orderBy('name')
                ->orderBy('id'))
            ->when($sort === 'series_desc', fn ($q) => $q
                ->orderByRaw('(SELECT bs.name FROM title_series bs WHERE bs.id = titles.title_series_id) DESC NULLS LAST')
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


        if ($tagKeyword !== '') {
            $query->whereHas('tags', function ($tagQuery) use ($tagKeyword) {
                $tagQuery->where('title_tags.name', 'ILIKE', "%{$tagKeyword}%");
            });
        }

        if ($categoryId !== 'all') {
            $query->where('title_category_id', $categoryId);
        }

        if ($seriesId !== 'all') {
            $query->where('title_series_id', $seriesId);
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
            $query->whereHas('studentTitles');
        }

        if ($usageStatus === 'unused') {
            $query->whereDoesntHave('studentTitles');
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
            'rows' => $rows->map(function (Title $title) {
                $requirements = $title->requirements->map(function (TitleRequirement $requirement) {
                    return [
                        'id' => $requirement->id,
                        'title_requirement_type_id' => $requirement->title_requirement_type_id,
                        'requirement_type' => $requirement->requirement_type,
                        'requirement_type_name' => $requirement->requirementType?->name ?? $requirement->requirement_type,
                        'requirement_value' => $requirement->requirement_value,
                    ];
                })->values();

                $firstRequirement = $requirements->first();

                return [
                    'id' => $title->id,
                    'category_id' => $title->title_category_id,
                    'category_name' => $title->category?->name,
                    'series_id' => $title->title_series_id,
                    'series_name' => $title->series?->name,
                    'code' => $title->code,
                    'name' => $title->name,
                    'level' => $title->level,
                    'description' => $title->description,
                    'image_path' => $this->resolveTitleImageUrl($title->image_path),
                    'requirements' => $requirements,
                    'requirement_type' => $firstRequirement['requirement_type'] ?? null,
                    'requirement_type_name' => $firstRequirement['requirement_type_name'] ?? null,
                    'requirement_value' => $firstRequirement['requirement_value'] ?? null,
                    'point_reward' => $title->point_reward,
                    'active_holder_count' => $title->active_holder_count ?? 0,
                    'cumulative_grant_count' => $title->cumulative_grant_count ?? 0,
                    'grant_method' => $title->grant_method?->value ?? $title->grant_method,
                    'acquisition_message' => $title->acquisition_message,
                    'allow_regrant' => (bool) $title->allow_regrant,
                    'notify_on_grant' => (bool) $title->notify_on_grant,
                    'is_limited' => (bool) $title->is_limited,
                    'start_date' => $title->start_date,
                    'end_date' => $title->end_date,
                    'display_order' => $title->display_order,
                    'is_active' => (bool) $title->is_active,
                    'condition_operator' => $title->condition_operator ?? 'and',
                    'tags' => $title->tags->map(fn (TitleTag $tag) => ['id' => $tag->id, 'name' => $tag->name])->values(),
                ];
            }),
            'summary' => [
                'total' => Title::count(),
                'current_holdings' => DB::table('student_titles')->where('status', 'active')->count(),
                'monthly_grants' => DB::table('student_titles')
                    ->whereBetween('acquired_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'monthly_removals' => DB::table('student_titles')
                    ->whereNotNull('removed_at')
                    ->whereBetween('removed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'previous_month_grants' => DB::table('student_titles')
                    ->whereBetween('acquired_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
                    ->count(),
                'previous_month_removals' => DB::table('student_titles')
                    ->whereNotNull('removed_at')
                    ->whereBetween('removed_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeRequirementInputs($request);

        $validated = $request->validate([
            'title_category_id' => ['required', 'exists:title_categories,id'],
            'title_series_id' => ['nullable', 'exists:title_series,id'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string'],
            'acquisition_message' => ['nullable', 'string'],
            'grant_method' => ['required', Rule::in(['auto', 'manual', 'both'])],
            'allow_regrant' => ['boolean'],
            'notify_on_grant' => ['boolean'],
            'tag_ids' => ['nullable', 'array', 'max:4'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:title_tags,id'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'condition_operator' => ['required', Rule::in(['and', 'or'])],
            'requirements' => ['nullable', 'array', 'max:3'],
            'requirements.*.title_requirement_type_id' => ['required_with:requirements', 'exists:title_requirement_types,id'],
            'requirements.*.requirement_value' => ['nullable', 'integer', 'min:0'],
            'point_reward' => ['nullable', 'integer', 'min:0'],
            'is_limited' => ['boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $title = Title::create([
                'title_category_id' => $validated['title_category_id'],
                'title_series_id' => $validated['title_series_id'] ?? null,
                'code' => $this->generateTitleCode(),
                'name' => $validated['name'],
                'level' => $validated['level'],
                'rarity' => $this->resolveRarity((int) $validated['level'], (bool) ($validated['is_limited'] ?? false)),
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
                'display_order' => (Title::max('display_order') ?? 0) + 1,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            if (!empty($validated['image_file'])) {
                $path = $validated['image_file']->storeAs(
                    'titles/' . $title->id,
                    'title.png',
                    's3'
                );

                $title->update([
                    'image_path' => $path,
                ]);
            }

            $this->syncRequirements($title, $validated['requirements'] ?? []);
            $title->tags()->sync($validated['tag_ids'] ?? []);
        });

        return response()->json(['message' => '保存しました。']);
    }

    public function update(Request $request, Title $title)
    {
        $this->normalizeRequirementInputs($request);

        $validated = $request->validate([
            'title_category_id' => ['required', 'exists:title_categories,id'],
            'title_series_id' => ['nullable', 'exists:title_series,id'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string'],
            'acquisition_message' => ['nullable', 'string'],
            'grant_method' => ['required', Rule::in(['auto', 'manual', 'both'])],
            'allow_regrant' => ['boolean'],
            'notify_on_grant' => ['boolean'],
            'tag_ids' => ['nullable', 'array', 'max:4'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:title_tags,id'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'condition_operator' => ['required', Rule::in(['and', 'or'])],
            'requirements' => ['nullable', 'array', 'max:3'],
            'requirements.*.title_requirement_type_id' => ['required_with:requirements', 'exists:title_requirement_types,id'],
            'requirements.*.requirement_value' => ['nullable', 'integer', 'min:0'],
            'point_reward' => ['nullable', 'integer', 'min:0'],
            'is_limited' => ['boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated, $title) {
            $title->update([
                'title_category_id' => $validated['title_category_id'],
                'title_series_id' => $validated['title_series_id'] ?? null,
                'name' => $validated['name'],
                'level' => $validated['level'],
                'rarity' => $this->resolveRarity((int) $validated['level'], (bool) ($validated['is_limited'] ?? false)),
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
                    'titles/' . $title->id,
                    'title.png',
                    's3'
                );

                $title->update([
                    'image_path' => $path,
                ]);
            }

            $this->syncRequirements($title, $validated['requirements'] ?? []);
            if (array_key_exists('tag_ids', $validated)) {
                $title->tags()->sync($validated['tag_ids']);
            }
        });

        return response()->json(['message' => '更新しました。']);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:titles,id'],
            'items.*.display_order' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                Title::whereKey($item['id'])->update([
                    'display_order' => $item['display_order'],
                ]);
            }
        });

        return response()->json(['message' => '並び順を更新しました。']);
    }

    public function duplicate(Title $title)
    {
        DB::transaction(function () use ($title) {
            $title->load(['requirements', 'tags']);

            $newTitle = $title->replicate();
            $newTitle->code = $this->generateTitleCode();
            $newTitle->name = $title->name . '（コピー）';
            $newTitle->display_order = (Title::max('display_order') ?? 0) + 1;
            $newTitle->image_path = null;
            $newTitle->save();

            if ($title->image_path) {
                $newImagePath = 'titles/' . $newTitle->id . '/title.png';

                if (Storage::disk('s3')->exists($title->image_path)) {
                    Storage::disk('s3')->copy($title->image_path, $newImagePath);

                    $newTitle->update([
                        'image_path' => $newImagePath,
                    ]);
                }
            }

            $newTitle->tags()->sync($title->tags->pluck('id')->all());

            foreach ($title->requirements as $requirement) {
                $newRequirement = $requirement->replicate();
                $newRequirement->title_id = $newTitle->id;
                $newRequirement->save();
            }
        });

        return response()->json([
            'message' => '複製しました。',
        ]);
    }

    public function toggleActive(Title $title)
    {
        $title->update([
            'is_active' => !$title->is_active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['message' => $title->is_active ? '有効化しました。' : '無効化しました。']);
    }

    public function destroy(Title $title)
    {
        if ($title->studentTitles()->exists()) {
            return response()->json([
                'message' => '生徒に付与済みのため削除できません。',
            ], 422);
        }

        Storage::disk('s3')->deleteDirectory(
            'titles/' . $title->id
        );

        DB::transaction(function () use ($title) {
            $title->tags()->detach();
            $title->requirements()->delete();
            $title->delete();
        });

        return response()->json([
            'message' => '削除しました。',
        ]);
    }


    public function bulkActivate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:titles,id'],
        ]);

        Title::whereIn('id', $validated['ids'])->update([
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
            'ids.*' => ['required', 'integer', 'exists:titles,id'],
        ]);

        Title::whereIn('id', $validated['ids'])->update([
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
            'ids.*' => ['required', 'integer', 'exists:titles,id'],
        ]);

        $titles = Title::with(['studentTitles'])
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($titles as $title) {
            if ($title->studentTitles->isNotEmpty()) {
                return response()->json([
                    'message' => '生徒に付与済みの称号があるため削除できません。',
                ], 422);
            }
        }

        DB::transaction(function () use ($titles) {
            foreach ($titles as $title) {
                Storage::disk('s3')->deleteDirectory(
                    'titles/' . $title->id
                );

                $title->tags()->detach();
                $title->requirements()->delete();
                $title->delete();
            }
        });

        return response()->json([
            'message' => '一括削除しました。',
        ]);
    }

    private function syncRequirements(Title $title, array $requirements): void
    {
        $title->requirements()->delete();

        foreach (array_slice($requirements, 0, 3) as $item) {
            if (empty($item['title_requirement_type_id'])) {
                continue;
            }

            $type = TitleRequirementType::find($item['title_requirement_type_id']);

            TitleRequirement::create([
                'title_id' => $title->id,
                'title_requirement_type_id' => $item['title_requirement_type_id'],
                'requirement_type' => $type?->code ?? '',
                'requirement_value' => $this->normalizeRequirementValue($item['requirement_value'] ?? null),
            ]);
        }
    }

    private function resolveTitleImageUrl(?string $path): ?string
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
     * 称号コードを LF-TTL-001 形式で重複なく自動採番する。
     */
    private function exportCsv($rows)
    {
        $filename = 'title-list-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'コード', '称号名', 'カテゴリ', 'シリーズ', '称号ランク',
                '付与方法', '一般/限定', '獲得条件', '獲得ポイント',
                '現在保有者数', '累計獲得数', '状態', '開始日', '終了日', '説明',
            ]);

            foreach ($rows as $title) {
                $requirements = $title->requirements->map(function ($requirement) {
                    $name = $requirement->requirementType?->name ?? $requirement->requirement_type ?? '条件';
                    return $requirement->requirement_value
                        ? $name . '：' . $requirement->requirement_value
                        : $name;
                })->implode($title->condition_operator === 'or' ? ' OR ' : ' AND ');

                fputcsv($handle, [
                    $title->id,
                    $title->code,
                    $title->name,
                    $title->category?->name,
                    $title->series?->name,
                    '★' . min(5, max(1, (int) $title->level)),
                    match ($title->grant_method?->value ?? $title->grant_method) {
                        'auto' => '自動',
                        'manual' => '手動',
                        default => '自動・手動',
                    },
                    $title->is_limited ? '限定' : '一般',
                    $requirements,
                    $title->point_reward,
                    $title->active_holder_count ?? 0,
                    $title->cumulative_grant_count ?? 0,
                    $title->is_active ? '有効' : '無効',
                    $title->start_date,
                    $title->end_date,
                    $title->description,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * わくわく側の難易度レベルから、旧称号管理でも必須のレア度を決定する。
     * 限定称号はレベルにかかわらず limited とする。
     */
    /**
     * 画面上の称号ランク（1〜5）を、既存DB互換のrarityへ変換する。
     * 限定状態はis_limitedで管理し、rarityには混在させない。
     */
    private function resolveRarity(int $level, bool $isLimited): string
    {
        return match (min(5, max(1, $level))) {
            5 => 'legend',
            4 => 'epic',
            3 => 'rare',
            default => 'normal',
        };
    }

    /**
     * 「5回」「30日」など旧UI由来の入力から数値部分だけを抽出する。
     */
    private function normalizeRequirementValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9-]/u', '', (string) $value);

        return $normalized === '' ? null : max(0, (int) $normalized);
    }

    /**
     * Laravelのバリデーション前に獲得条件値を整数へ正規化する。
     */
    private function normalizeRequirementInputs(Request $request): void
    {
        $requirements = collect($request->input('requirements', []))
            ->map(function ($requirement) {
                if (!is_array($requirement)) {
                    return $requirement;
                }

                $requirement['requirement_value'] = $this->normalizeRequirementValue(
                    $requirement['requirement_value'] ?? null
                );

                return $requirement;
            })
            ->all();

        $request->merge(['requirements' => $requirements]);
    }

    private function generateTitleCode(): string
    {
        $numbers = Title::query()
            ->lockForUpdate()
            ->where('code', 'like', 'LF-TTL-%')
            ->pluck('code')
            ->map(function (?string $code): int {
                return preg_match('/^LF-TTL-(\d+)$/', (string) $code, $matches)
                    ? (int) $matches[1]
                    : 0;
            });

        $nextNumber = ($numbers->max() ?? 0) + 1;

        do {
            $code = 'LF-TTL-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Title::query()->where('code', $code)->exists());

        return $code;
    }

}