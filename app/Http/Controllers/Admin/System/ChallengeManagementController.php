<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Challenge;
use App\Models\ChallengeCategory;
use App\Models\ChallengeReward;
use App\Models\Title;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChallengeManagementController extends Controller
{
    public function index()
    {
        return view('admin.system.challenges.index', [
            'categories' => ChallengeCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'badges' => Badge::where('is_active', true)->orderBy('id')->get(),
            'titles' => Title::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function list(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $categoryId = $request->query('category_id', 'all');
        $difficulty = $request->query('difficulty', 'all');
        $badgeId = $request->query('badge_id', 'all');
        $titleId = $request->query('title_id', 'all');
        $status = $request->query('status', 'all');

        $query = Challenge::with(['category', 'rewards.badge', 'rewards.title'])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            });
        }

        if ($categoryId !== 'all') {
            $query->where('challenge_category_id', $categoryId);
        }

        if ($difficulty !== 'all') {
            $query->where('difficulty', $difficulty);
        }

        if ($badgeId !== 'all') {
            $query->whereHas('rewards', function ($q) use ($badgeId) {
                $q->where('badge_id', $badgeId);
            });
        }

        if ($titleId !== 'all') {
            $query->whereHas('rewards', function ($q) use ($titleId) {
                $q->where('title_id', $titleId);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $rows = $query->get();

        return response()->json([
            'rows' => $rows->map(function (Challenge $challenge) {
                $reward = $challenge->rewards->first();

                return [
                    'id' => $challenge->id,
                    'category_id' => $challenge->challenge_category_id,
                    'category_name' => $challenge->category?->name,
                    'code' => $challenge->code,
                    'name' => $challenge->name,
                    'difficulty' => $challenge->difficulty,
                    'description' => $challenge->description,
                    'icon_path' => $challenge->icon_path
                        ? Storage::disk('s3')->temporaryUrl($challenge->icon_path, now()->addMinutes(30))
                        : null,
                    'challenge_type' => $challenge->challenge_type,
                    'requirement_description' => $challenge->requirement_description,
                    'max_score' => $challenge->max_score,
                    'passing_score' => $challenge->passing_score,
                    'sort_order' => $challenge->sort_order,
                    'badge_id' => $reward?->badge_id,
                    'badge_name' => $reward?->badge?->name,
                    'title_id' => $reward?->title_id,
                    'title_name' => $reward?->title?->name,
                    'point_amount' => $reward?->point_amount ?? 0,
                    'is_active' => (bool) $challenge->is_active,
                ];
            }),
            'summary' => [
                'total' => Challenge::count(),
                'active' => Challenge::where('is_active', true)->count(),
                'inactive' => Challenge::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'challenge_category_id' => ['required', 'exists:challenge_categories,id'],
            'code' => ['required', 'string', 'max:255', 'unique:challenges,code'],
            'name' => ['required', 'string', 'max:255'],
            'difficulty' => ['required', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string'],
            'icon_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],


            'challenge_type' => ['required', 'string', 'max:255'],
            'requirement_description' => ['nullable', 'string'],
            'max_score' => ['nullable', 'integer', 'min:0'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'lte:max_score'],
            'badge_id' => ['nullable', 'exists:badges,id'],
            'title_id' => ['nullable', 'exists:titles,id'],
            'point_amount' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'passing_score.lte' => '合格点は、満点・問題数以下で入力してください。',
            'max_score.integer' => '満点・問題数は数値で入力してください。',
            'passing_score.integer' => '合格点は数値で入力してください。',
            'icon_file.image' => 'アイコン画像は画像ファイルを選択してください。',
            'icon_file.mimes' => 'アイコン画像は JPG・JPEG・PNG・WEBP を選択してください。',
            'icon_file.max' => 'アイコン画像は5MB以内で選択してください。',
            'icon_file.uploaded' => 'アイコン画像のアップロードに失敗しました。画像サイズを小さくしてください。',
            'code.unique' => 'このコードはすでに使用されています。',
            'code.required' => 'コードを入力してください。',
            'name.required' => 'チャレンジ名を入力してください。',
            'difficulty.required' => '難易度を選択してください。',
            'challenge_category_id.required' => 'カテゴリを選択してください。',
            'challenge_type.required' => '合格判定タイプを選択してください。',

        ]);

        DB::transaction(function () use ($validated) {
            $challenge = Challenge::create([
                'challenge_category_id' => $validated['challenge_category_id'],
                'code' => $validated['code'],
                'name' => $validated['name'],
                'difficulty' => $validated['difficulty'],
                'description' => $validated['description'] ?? null,
                'icon_path' => null,
                'challenge_type' => $validated['challenge_type'],
                'requirement_description' => $validated['requirement_description'] ?? null,
                'max_score' => $validated['max_score'] ?? null,
                'passing_score' => $validated['passing_score'] ?? null,
                'sort_order' => (Challenge::max('sort_order') ?? 0) + 1,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            if (!empty($validated['icon_file'])) {
                $path = $validated['icon_file']->storeAs(
                    'challenges/' . $challenge->id,
                    'challenges.png',
                    's3'
                );

                $challenge->update([
                    'icon_path' => $path,
                ]);
            }

            ChallengeReward::create([
                'challenge_id' => $challenge->id,
                'badge_id' => $validated['badge_id'] ?? null,
                'title_id' => $validated['title_id'] ?? null,
                'point_amount' => $validated['point_amount'] ?? 0,
                'sort_order' => 1,
            ]);
        });

        return response()->json(['message' => '保存しました。']);
    }

    public function update(Request $request, Challenge $challenge)
    {
        $validated = $request->validate([
            'challenge_category_id' => ['required', 'exists:challenge_categories,id'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('challenges', 'code')->ignore($challenge->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'difficulty' => ['required', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string'],
            'icon_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'challenge_type' => ['required', 'string', 'max:255'],
            'requirement_description' => ['nullable', 'string'],
            'max_score' => ['nullable', 'integer', 'min:0'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'lte:max_score'],
            'badge_id' => ['nullable', 'exists:badges,id'],
            'title_id' => ['nullable', 'exists:titles,id'],
            'point_amount' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'passing_score.lte' => '合格点は、満点・問題数以下で入力してください。',
            'max_score.integer' => '満点・問題数は数値で入力してください。',
            'passing_score.integer' => '合格点は数値で入力してください。',
            'icon_file.image' => 'アイコン画像は画像ファイルを選択してください。',
            'icon_file.mimes' => 'アイコン画像は JPG・JPEG・PNG・WEBP を選択してください。',
            'icon_file.max' => 'アイコン画像は5MB以内で選択してください。',
            'icon_file.uploaded' => 'アイコン画像のアップロードに失敗しました。画像サイズを小さくしてください。',
            'code.unique' => 'このコードはすでに使用されています。',
            'code.required' => 'コードを入力してください。',
            'name.required' => 'チャレンジ名を入力してください。',
            'difficulty.required' => '難易度を選択してください。',
            'challenge_category_id.required' => 'カテゴリを選択してください。',
            'challenge_type.required' => '合格判定タイプを選択してください。',


        ]);

        DB::transaction(function () use ($validated, $challenge) {
            $challenge->update([
                'challenge_category_id' => $validated['challenge_category_id'],
                'code' => $validated['code'],
                'name' => $validated['name'],
                'difficulty' => $validated['difficulty'],
                'description' => $validated['description'] ?? null,
                'challenge_type' => $validated['challenge_type'],
                'requirement_description' => $validated['requirement_description'] ?? null,
                'max_score' => $validated['max_score'] ?? null,
                'passing_score' => $validated['passing_score'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            if (!empty($validated['icon_file'])) {
                $path = $validated['icon_file']->storeAs(
                    'challenges/' . $challenge->id,
                    'challenges.png',
                    's3'
                );

                $challenge->update([
                    'icon_path' => $path,
                ]);
            }

            ChallengeReward::updateOrCreate(
                ['challenge_id' => $challenge->id],
                [
                    'badge_id' => $validated['badge_id'] ?? null,
                    'title_id' => $validated['title_id'] ?? null,
                    'point_amount' => $validated['point_amount'] ?? 0,
                    'sort_order' => 1,
                ]
            );
        });

        return response()->json(['message' => '更新しました。']);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:challenges,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                Challenge::whereKey($item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return response()->json(['message' => '並び順を更新しました。']);
    }

    public function duplicate(Challenge $challenge)
    {
        DB::transaction(function () use ($challenge) {
            $newChallenge = $challenge->replicate();

            $newChallenge->code = $challenge->code . '_copy_' . now()->format('His');
            $newChallenge->name = $challenge->name . '（コピー）';
            $newChallenge->sort_order = (Challenge::max('sort_order') ?? 0) + 1;
            $newChallenge->icon_path = null;
            $newChallenge->save();

            if ($challenge->icon_path) {
                $newIconPath = 'challenges/' . $newChallenge->id . '/challenges.png';

                if (Storage::disk('s3')->exists($challenge->icon_path)) {
                    Storage::disk('s3')->copy($challenge->icon_path, $newIconPath);

                    $newChallenge->update([
                        'icon_path' => $newIconPath,
                    ]);
                }
            }

            foreach ($challenge->rewards as $reward) {
                $newReward = $reward->replicate();
                $newReward->challenge_id = $newChallenge->id;
                $newReward->save();
            }
        });

        return response()->json([
            'message' => '複製しました。',
        ]);
    }

    public function deactivate(Challenge $challenge)
    {
        $challenge->update([
            'is_active' => false,
        ]);

        return response()->json(['message' => '無効化しました。']);
    }

    public function destroy(Challenge $challenge)
    {
        if ($challenge->reservations()->exists() || $challenge->logs()->exists()) {
            return response()->json([
                'message' => '予約または履歴に紐づいているため削除できません。',
            ], 422);
        }

        Storage::disk('s3')->deleteDirectory(
            'challenges/' . $challenge->id
        );

        $challenge->delete();

        return response()->json([
            'message' => '削除しました。',
        ]);
    }

    public function bulkDeactivate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:challenges,id'],
        ]);

        Challenge::whereIn('id', $validated['ids'])->update([
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
            'ids.*' => ['required', 'integer', 'exists:challenges,id'],
        ]);

        $challenges = Challenge::with(['reservations', 'logs'])
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($challenges as $challenge) {
            if ($challenge->reservations->isNotEmpty() || $challenge->logs->isNotEmpty()) {
                return response()->json([
                    'message' => '予約または履歴に紐づいているチャレンジがあるため削除できません。',
                ], 422);
            }
        }

        DB::transaction(function () use ($challenges) {
            foreach ($challenges as $challenge) {
                Storage::disk('s3')->deleteDirectory(
                    'challenges/' . $challenge->id
                );

                $challenge->delete();
            }
        });

        return response()->json([
            'message' => '一括削除しました。',
        ]);
    }
}