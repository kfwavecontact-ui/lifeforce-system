<?php

namespace App\Http\Controllers\Admin\System;

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

class BadgeManagementController extends Controller
{
    public function index()
    {
        return view('admin.system.badges.index', [
            'categories' => BadgeCategory::where('is_active', true)
                ->orderBy('display_order')
                ->get(),

            'series' => BadgeSeries::orderBy('display_order')
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

        $query = Badge::with(['category', 'series', 'requirements.requirementType'])
            ->withCount('studentBadges')
            ->orderBy('display_order')
            ->orderBy('id');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
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

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $rows = $query->get();

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
                    'acquired_count' => $badge->student_badges_count ?? 0,
                    'is_limited' => (bool) $badge->is_limited,
                    'start_date' => $badge->start_date,
                    'end_date' => $badge->end_date,
                    'display_order' => $badge->display_order,
                    'is_active' => (bool) $badge->is_active,
                ];
            }),
            'summary' => [
                'total' => Badge::count(),
                'active' => Badge::where('is_active', true)->count(),
                'inactive' => Badge::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'badge_category_id' => ['required', 'exists:badge_categories,id'],
            'badge_series_id' => ['nullable', 'exists:badge_series,id'],
            'code' => ['required', 'string', 'max:255', 'unique:badges,code'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:99'],
            'description' => ['nullable', 'string'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
                'code' => $validated['code'],
                'name' => $validated['name'],
                'level' => $validated['level'],
                'description' => $validated['description'] ?? null,
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
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('badges', 'code')->ignore($badge->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:99'],
            'description' => ['nullable', 'string'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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
                'code' => $validated['code'],
                'name' => $validated['name'],
                'level' => $validated['level'],
                'description' => $validated['description'] ?? null,
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
            $newBadge->code = $badge->code . '_copy_' . now()->format('His');
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

    public function deactivate(Badge $badge)
    {
        $badge->update([
            'is_active' => false,
        ]);

        return response()->json(['message' => '無効化しました。']);
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

    public function bulkDeactivate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:badges,id'],
        ]);

        Badge::whereIn('id', $validated['ids'])->update([
            'is_active' => false,
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
}