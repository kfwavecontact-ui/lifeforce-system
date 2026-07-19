<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Models\BoardPost;
use App\Services\BoardMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 運営 ＞ 連絡 ＞ 掲示板を管理するController。
 * 一覧・検索・列フィルタ・ソート・登録・編集・複製・削除を担当します。
 */
class BoardController extends Controller
{
    public function __construct(private readonly BoardMediaService $mediaService)
    {
    }

    public function index(Request $request): View
    {
        $query = BoardPost::query()
            ->with(['targets', 'attachments'])
            ->withCount('reads');

        $this->applySearchFilters($query, $request);

        $allowedSorts = ['id', 'title', 'category', 'publish_from', 'publish_to', 'status', 'updated_at'];
        $requestedSort = (string) $request->input('sort', '');
        $hasRequestedSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasRequestedSort ? $requestedSort : 'id';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        /*
         * 通常表示ではピン留めを優先します。
         * ただし、利用者が列ソートを選択した場合は、その列を最優先にしなければ
         * ID降順でもピン留め投稿が先頭に残り、正しい並び順になりません。
         */
        if (! $hasRequestedSort) {
            $query->orderByDesc('is_pinned')->orderByDesc('id');
        } else {
            $this->applySort($query, $sort, $direction);
        }

        $posts = $query
            ->paginate(50)
            ->withQueryString();

        $posts->getCollection()->transform(function (BoardPost $post) {
            $post->target_label = $this->targetLabel($post);
            $post->target_count = $this->calculateTargetCount($post);
            $post->read_rate = $post->target_count > 0
                ? round(($post->reads_count / $post->target_count) * 100, 1)
                : 0;
            $post->attachment_items = $post->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'url' => $this->mediaService->url($attachment->storage_key),
                'size_bytes' => $attachment->size_bytes,
            ])->values();

            return $post;
        });

        return view('admin.operations.boards.index', [
            'posts' => $posts,
            'schools' => DB::table('schools')->orderBy('name')->get(['id', 'name']),
            'grades' => DB::table('grades')->orderBy('id')->get(['id', 'name']),
            'courses' => DB::table('courses')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'students' => DB::table('students')->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(['id', 'student_code', 'last_name', 'first_name']),
            'categories' => ['教室からのお知らせ', '休講・振替', 'イベント', '教材・持ち物', '重要連絡', 'その他'],
            'filters' => $request->all(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePost($request);

        DB::transaction(function () use ($request, $validated) {
            $post = BoardPost::create([
                ...$this->postValues($validated),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncTargets($post, $validated);
            $this->storeAttachments($request, $post);
        });

        return back()->with('success', '掲示板を登録しました。');
    }

    public function update(Request $request, BoardPost $boardPost): RedirectResponse
    {
        $validated = $this->validatePost($request);

        DB::transaction(function () use ($request, $validated, $boardPost) {
            $boardPost->update([
                ...$this->postValues($validated),
                'updated_by' => auth()->id(),
            ]);
            $this->syncTargets($boardPost, $validated);
            $this->deleteSelectedAttachments($request, $boardPost);
            $this->storeAttachments($request, $boardPost);
        });

        return back()->with('success', '掲示板を更新しました。');
    }

    public function duplicate(BoardPost $boardPost): RedirectResponse
    {
        $boardPost->load(['targets', 'attachments']);

        DB::transaction(function () use ($boardPost) {
            $copy = $boardPost->replicate([
                'status', 'publish_from', 'publish_to', 'attachment_name', 'attachment_path',
                'created_by', 'updated_by', 'created_at', 'updated_at',
            ]);
            $copy->title = $boardPost->title . '（複製）';
            $copy->status = 'draft';
            $copy->publish_from = null;
            $copy->publish_to = null;
            $copy->attachment_name = null;
            $copy->attachment_path = null;
            $copy->created_by = auth()->id();
            $copy->updated_by = auth()->id();
            $copy->save();

            foreach ($boardPost->targets as $target) {
                $copy->targets()->create([
                    'target_type' => $target->target_type,
                    'target_id' => $target->target_id,
                ]);
            }

            foreach ($boardPost->attachments as $index => $attachment) {
                $copy->attachments()->create([
                    'original_name' => $attachment->original_name,
                    'storage_key' => $this->mediaService->copy($attachment->storage_key, $copy->id),
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'sort_order' => $index,
                ]);
            }
        });

        return back()->with('success', '掲示板を複製しました。公開期間は空欄、状態は下書きです。');
    }

    public function destroy(BoardPost $boardPost): RedirectResponse
    {
        $boardPost->load('attachments');
        DB::transaction(function () use ($boardPost) {
            foreach ($boardPost->attachments as $attachment) {
                $this->mediaService->delete($attachment->storage_key);
            }
            $boardPost->delete();
        });

        return back()->with('success', '掲示板を削除しました。');
    }

    /**
     * 一覧の列ソートを適用します。
     *
     * 同値データはIDで安定化し、日時の未設定値は昇順・降順とも末尾に配置します。
     * 状態は画面上の表示順（下書き→公開→公開終了）で並べます。
     */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        if (in_array($sort, ['publish_from', 'publish_to', 'updated_at'], true)) {
            $query->orderByRaw(sprintf('"%s" %s NULLS LAST', $sort, strtoupper($direction)));
        } elseif ($sort === 'status') {
            $statusOrder = "CASE status WHEN 'draft' THEN 1 WHEN 'published' THEN 2 WHEN 'closed' THEN 3 ELSE 4 END";
            $query->orderByRaw($statusOrder . ' ' . strtoupper($direction));
        } else {
            $query->orderBy($sort, $direction);
        }

        if ($sort !== 'id') {
            $query->orderBy('id', $direction);
        }
    }

    private function applySearchFilters(Builder $query, Request $request): void
    {
        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(fn ($subQuery) => $subQuery
                ->where('title', 'like', "%{$keyword}%")
                ->orWhere('body', 'like', "%{$keyword}%")
                ->orWhere('category', 'like', "%{$keyword}%"));
        }

        $categories = array_values(array_filter((array) $request->input('category_filter', [])));
        if ($request->filled('category')) {
            $categories[] = (string) $request->input('category');
        }
        if ($categories) {
            $query->whereIn('category', array_unique($categories));
        }

        $statuses = array_values(array_filter((array) $request->input('status_filter', [])));
        if ($request->filled('status')) {
            $statuses[] = (string) $request->input('status');
        }
        if ($statuses) {
            $query->whereIn('status', array_unique($statuses));
        }

        $targetTypes = array_values(array_filter((array) $request->input('target_type_filter', [])));
        if ($request->filled('target_type')) {
            $targetTypes[] = (string) $request->input('target_type');
        }
        if ($targetTypes) {
            $query->whereHas('targets', fn ($targetQuery) => $targetQuery->whereIn('target_type', array_unique($targetTypes)));
        }

        if ($request->filled('publish_from')) {
            $query->whereDate('publish_from', '>=', $request->input('publish_from'));
        }
        if ($request->filled('publish_to')) {
            $query->whereDate('publish_to', '<=', $request->input('publish_to'));
        }
        if ($request->filled('publish_from_filter')) {
            $query->whereDate('publish_from', '>=', $request->input('publish_from_filter'));
        }
        if ($request->filled('publish_to_filter')) {
            $query->whereDate('publish_to', '<=', $request->input('publish_to_filter'));
        }
        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->input('updated_from'));
        }
        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->input('updated_to'));
        }
        if ($request->filled('title_filter')) {
            $query->where('title', 'like', '%' . trim((string) $request->input('title_filter')) . '%');
        }
        if ($request->filled('id_min')) {
            $query->where('id', '>=', (int) $request->input('id_min'));
        }
        if ($request->filled('id_max')) {
            $query->where('id', '<=', (int) $request->input('id_max'));
        }
        if ($request->boolean('important') || $request->boolean('important_filter')) {
            $query->where('is_important', true);
        }
        if ($request->boolean('pinned') || $request->boolean('pinned_filter')) {
            $query->where('is_pinned', true);
        }
    }

    private function validatePost(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'status' => ['required', Rule::in(['draft', 'published', 'closed'])],
            'publish_from' => ['nullable', 'date'],
            'publish_to' => ['nullable', 'date', 'after_or_equal:publish_from'],
            'is_important' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'target_type' => ['required', Rule::in(['all', 'school', 'grade', 'course', 'student'])],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
            'remove_attachment_ids' => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['integer'],
        ]);
    }

    private function postValues(array $validated): array
    {
        return [
            'category' => $validated['category'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => $validated['status'],
            'publish_from' => $validated['publish_from'] ?? null,
            'publish_to' => $validated['publish_to'] ?? null,
            'is_important' => (bool) ($validated['is_important'] ?? false),
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
        ];
    }

    private function storeAttachments(Request $request, BoardPost $post): void
    {
        $startOrder = (int) $post->attachments()->max('sort_order') + 1;
        foreach ($request->file('attachments', []) as $index => $file) {
            $post->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'storage_key' => $this->mediaService->upload($file, $post->id),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'sort_order' => $startOrder + $index,
            ]);
        }
    }

    private function deleteSelectedAttachments(Request $request, BoardPost $post): void
    {
        $ids = array_values(array_filter((array) $request->input('remove_attachment_ids', [])));
        if (! $ids) {
            return;
        }

        $attachments = $post->attachments()->whereIn('id', $ids)->get();
        foreach ($attachments as $attachment) {
            $this->mediaService->delete($attachment->storage_key);
            $attachment->delete();
        }
    }

    private function syncTargets(BoardPost $post, array $validated): void
    {
        $post->targets()->delete();
        $type = $validated['target_type'];
        $ids = $type === 'all' ? [null] : array_values(array_unique(array_filter($validated['target_ids'] ?? [])));

        if ($type !== 'all' && count($ids) === 0) {
            abort(422, '公開対象を1件以上選択してください。');
        }

        foreach ($ids as $id) {
            $post->targets()->create(['target_type' => $type, 'target_id' => $id]);
        }
    }

    private function targetLabel(BoardPost $post): string
    {
        return match (optional($post->targets->first())->target_type ?? 'all') {
            'school' => '教室', 'grade' => '学年', 'course' => 'コース', 'student' => '生徒個別', default => '全体',
        };
    }

    private function calculateTargetCount(BoardPost $post): int
    {
        $targets = $post->targets;
        $type = optional($targets->first())->target_type ?? 'all';
        $ids = $targets->pluck('target_id')->filter()->values();

        return match ($type) {
            'school' => DB::table('students')->where('is_active', true)->whereIn('school_id', $ids)->count(),
            'grade' => DB::table('students')->where('is_active', true)->whereIn('grade_id', $ids)->count(),
            'course' => DB::table('student_course_contracts')->where('is_active', true)->whereIn('course_id', $ids)->distinct('student_id')->count('student_id'),
            'student' => $ids->count(),
            default => DB::table('students')->where('is_active', true)->count(),
        };
    }
}
