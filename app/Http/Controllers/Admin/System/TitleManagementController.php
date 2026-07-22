<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\Title;
use App\Models\Event;
use App\Models\StudentTitle;
use App\Models\TitleTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TitleManagementController extends Controller
{
    private array $rarities = [
        1 => '★1',
        2 => '★2',
        3 => '★3',
        4 => '★4',
        5 => '★5',
    ];

    public function index()
    {
        return view('admin.system.titles.index', [
            'rarities' => $this->rarities,
            'titleTags' => TitleTag::where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function list(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $tagKeyword = trim((string) $request->query('tag_keyword', ''));
        $eventKeyword = trim((string) $request->query('event_keyword', ''));
        $rarity = $request->query('rarity', 'all');
        $status = $request->query('status', 'all');

        $query = Title::with(['tags', 'events.schedules'])
            ->withCount('studentTitles')
            ->orderBy('display_order')
            ->orderBy('id');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($tagKeyword !== '') {
            $query->whereHas('tags', function ($q) use ($tagKeyword) {
                $q->where('title_tags.name', 'like', "%{$tagKeyword}%");
            });
        }

        if ($eventKeyword !== '') {
            $query->whereHas('events', function ($q) use ($eventKeyword) {
                $q->where('events.title', 'like', "%{$eventKeyword}%");
            });
        }

        if ($rarity !== 'all') {
            $query->where('level', (int) $rarity);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $rows = $query->get();

        return response()->json([
            'rows' => $rows->map(function (Title $title) {
                return [
                    'id' => $title->id,
                    'name' => $title->name,
                    'description' => $title->description,
                    'image_path' => $this->resolveTitleImageUrl($title->image_path),
                    'rarity' => min(5, max(1, (int) $title->level)),
                    'rarity_name' => '★' . min(5, max(1, (int) $title->level)),
                    'point_reward' => $title->point_reward ?? 0,
                    'acquired_count' => $title->student_titles_count ?? 0,
                    'tags' => $title->tags->map(function (TitleTag $tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ];
                    })->values(),
                    'events' => $title->events->map(function (Event $event) {
                        $startAt = $event->schedules
                            ->sortBy('start_at')
                            ->first()
                            ?->start_at;

                        return [
                            'id' => $event->id,
                            'title' => $event->title,
                            'start_at' => $startAt ? $startAt->format('Y/m/d H:i') : '日付未設定',
                        ];
                    })->values(),
                    'event_summary' => $this->formatEventSummary($title),
                    'display_order' => $title->display_order,
                    'is_active' => (bool) $title->is_active,
                ];
            }),
            'summary' => [
                'total' => Title::count(),
                'active' => Title::where('is_active', true)->count(),
                'inactive' => Title::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'rarity' => ['required', 'integer', 'min:1', 'max:5'],
            'point_reward' => ['nullable', 'integer', 'min:0'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:title_tags,id'],
            'event_ids' => ['array'],
            'event_ids.*' => ['integer', 'distinct', 'exists:events,id'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $title = Title::create([
                'code' => $this->generateTitleCode(),
                'grant_method' => 'both',
                'name' => $validated['name'],
                'level' => (int) $validated['rarity'],
                'description' => $validated['description'] ?? null,
                'image_path' => null,
                'rarity' => $this->rarityFromLevel((int) $validated['rarity']),
                'level' => (int) $validated['rarity'],
                'point_reward' => $validated['point_reward'] ?? 0,
                'display_order' => (Title::count() === 0 ? 1 : (Title::max('display_order') + 1)),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
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

            $title->events()->sync(
                collect($validated['event_ids'] ?? [])
                    ->values()
                    ->all()
            );

            $title->tags()->sync(
                collect($validated['tag_ids'] ?? [])
                    ->take(4)
                    ->values()
                    ->all()
            );
        });

        return response()->json(['message' => '保存しました。']);
    }

    public function update(Request $request, Title $title)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'rarity' => ['required', 'integer', 'min:1', 'max:5'],
            'point_reward' => ['nullable', 'integer', 'min:0'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:title_tags,id'],
            'is_active' => ['boolean'],
            'event_ids' => ['array'],
            'event_ids.*' => ['integer', 'distinct', 'exists:events,id'],
        ]);

        DB::transaction(function () use ($validated, $title) {
            $title->update([
                'grant_method' => 'both',
                'name' => $validated['name'],
                'level' => (int) $validated['rarity'],
                'description' => $validated['description'] ?? null,
                'rarity' => $this->rarityFromLevel((int) $validated['rarity']),
                'level' => (int) $validated['rarity'],
                'point_reward' => $validated['point_reward'] ?? 0,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'updated_by' => auth()->id(),
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

            $title->events()->sync(
                collect($validated['event_ids'] ?? [])
                    ->values()
                    ->all()
            );

            $title->tags()->sync(
                collect($validated['tag_ids'] ?? [])
                    ->take(4)
                    ->values()
                    ->all()
            );
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
        $title->loadMissing(['tags:id', 'events:id', 'requirements']);

        DB::transaction(function () use ($title) {
            $newTitle = $title->replicate();
            $newTitle->code = $this->generateTitleCode();
            $newTitle->name = $title->name . '（コピー）';
            $newTitle->display_order = (Title::max('display_order') ?? 0) + 1;
            $newTitle->image_path = null;
            $newTitle->created_by = auth()->id();
            $newTitle->updated_by = auth()->id();
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
            $newTitle->events()->sync($title->events->pluck('id')->all());

            foreach ($title->requirements as $requirement) {
                $copy = $requirement->replicate();
                $copy->title_id = $newTitle->id;
                $copy->save();
            }
        });

        return response()->json(['message' => '複製しました。']);
    }

    public function deactivate(Title $title)
    {
        $title->update([
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['message' => '無効化しました。']);
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
            $title->events()->detach();
            $title->requirements()->delete();
            $title->delete();
        });

        return response()->json(['message' => '削除しました。']);
    }

    public function searchEvents(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));

        $query = Event::query()
            ->where('is_active', true)
            ->orderByDesc('id');

        if ($keyword !== '') {
            $query->where('title', 'like', "%{$keyword}%");
        }

        return response()->json([
            'rows' => $query
                ->limit(20)
                ->get()
                ->map(function (Event $event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                    ];
                })
                ->values(),
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
        ]);

        return response()->json(['message' => '一括無効化しました。']);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:titles,id'],
        ]);

        $titles = Title::with('studentTitles')
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
                $title->events()->detach();
                $title->requirements()->delete();
                $title->delete();
            }
        });

        return response()->json(['message' => '一括削除しました。']);
    }

    private function formatEventSummary(Title $title): string
    {
        $events = $title->events;

        if ($events->isEmpty()) {
            return '-';
        }

        if ($events->count() <= 2) {
            return $events
                ->pluck('title')
                ->implode('、');
        }

        return $events
            ->take(2)
            ->pluck('title')
            ->implode('、') . ' ほか' . ($events->count() - 2) . '件';
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

    /**
     * 称号ランク（1〜5）を既存DB互換のrarityへ変換する。
     */
    private function rarityFromLevel(int $level): string
    {
        return match (min(5, max(1, $level))) {
            5 => 'legend',
            4 => 'epic',
            3 => 'rare',
            default => 'normal',
        };
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
}