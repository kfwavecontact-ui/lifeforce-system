<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 運営 ＞ 連絡 ＞ 一斉通知の一覧・登録・編集・複製・削除を担当します。
 * このPhaseでは送信処理は行わず、下書き・予約情報を安全に管理します。
 */
class BulkNotificationController extends Controller
{
    public function __construct(private readonly NotificationMediaService $mediaService)
    {
    }

    public function index(Request $request): View
    {
        $query = Notification::query()
            ->with(['notificationMaster', 'targets', 'attachments', 'boardPost'])
            ->withCount(['recipients', 'recipients as read_count' => fn ($q) => $q->whereNotNull('read_at'), 'recipients as confirmed_count' => fn ($q) => $q->whereNotNull('confirmed_at')])
            ->where('related_table', 'bulk_notification');

        $this->applyFilters($query, $request);

        $allowedSorts = ['id', 'title', 'scheduled_at', 'sent_at', 'notification_status', 'updated_at'];
        $requestedSort = (string) $request->input('sort', '');
        $sort = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'id';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderByRaw(sprintf('"%s" %s NULLS LAST', $sort, strtoupper($direction)));
        if ($sort !== 'id') {
            $query->orderBy('id', $direction);
        }

        $notifications = $query->paginate(50)->withQueryString();
        $notifications->getCollection()->transform(function (Notification $notification) {
            $notification->target_label = $this->targetLabel($notification);
            $notification->recipient_label = $this->recipientLabel($notification);
            $notification->attachment_items = $notification->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'url' => $this->mediaService->url($attachment->storage_key),
                'size_bytes' => $attachment->size_bytes,
            ])->values();
            return $notification;
        });

        return view('admin.operations.bulk-notifications.index', [
            'notifications' => $notifications,
            'notificationMasters' => DB::table('notification_masters')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'category']),
            'boards' => DB::table('board_posts')->orderByDesc('id')->get(['id', 'title']),
            'schools' => DB::table('schools')->orderBy('name')->get(['id', 'name']),
            'grades' => DB::table('grades')->orderBy('id')->get(['id', 'name']),
            'courses' => DB::table('courses')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'students' => DB::table('students')->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(['id', 'student_code', 'last_name', 'first_name']),
            'filters' => $request->all(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateNotification($request);
        DB::transaction(function () use ($request, $validated) {
            $notification = Notification::create($this->notificationValues($validated) + [
                'created_by_user_id' => $this->currentUserId(),
                'updated_by_user_id' => $this->currentUserId(),
                'related_table' => 'bulk_notification',
                'related_id' => null,
                'sent_at' => null,
            ]);
            $this->syncTargets($notification, $validated);
            $this->storeAttachments($request, $notification);
        });
        return back()->with('success', '一斉通知を登録しました。');
    }

    public function update(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->related_table === 'bulk_notification', 404);
        abort_if($notification->notification_status === 'sent', 422, '送信済み通知は編集できません。');
        $validated = $this->validateNotification($request, $notification);
        DB::transaction(function () use ($request, $validated, $notification) {
            $notification->update($this->notificationValues($validated) + ['updated_by_user_id' => $this->currentUserId()]);
            $this->syncTargets($notification, $validated);
            $this->deleteSelectedAttachments($request, $notification);
            $this->storeAttachments($request, $notification);
        });
        return back()->with('success', '一斉通知を更新しました。');
    }

    public function duplicate(Notification $notification): RedirectResponse
    {
        abort_unless($notification->related_table === 'bulk_notification', 404);
        $notification->load(['targets', 'attachments']);
        DB::transaction(function () use ($notification) {
            $copy = $notification->replicate(['scheduled_at', 'sent_at', 'notification_status', 'created_by_user_id', 'updated_by_user_id', 'created_at', 'updated_at']);
            $copy->title = $notification->title . '（複製）';
            $copy->scheduled_at = null;
            $copy->sent_at = null;
            $copy->notification_status = 'draft';
            $copy->created_by_user_id = $this->currentUserId();
            $copy->updated_by_user_id = $this->currentUserId();
            $copy->save();
            foreach ($notification->targets as $target) {
                $copy->targets()->create($target->only(['target_type', 'target_id', 'recipient_type']));
            }
            foreach ($notification->attachments as $index => $attachment) {
                $copy->attachments()->create([
                    'original_name' => $attachment->original_name,
                    'storage_key' => $this->mediaService->copy($attachment->storage_key, $copy->id),
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'sort_order' => $index,
                ]);
            }
        });
        return back()->with('success', '一斉通知を複製しました。送信日時は空欄、状態は下書きです。');
    }

    public function destroy(Notification $notification): RedirectResponse
    {
        abort_unless($notification->related_table === 'bulk_notification', 404);
        abort_if($notification->notification_status === 'sent', 422, '送信済み通知は削除できません。');
        $notification->load('attachments');
        DB::transaction(function () use ($notification) {
            foreach ($notification->attachments as $attachment) {
                $this->mediaService->delete($attachment->storage_key);
            }
            $notification->delete();
        });
        return back()->with('success', '一斉通知を削除しました。');
    }

    private function validateNotification(Request $request, ?Notification $notification = null): array
    {
        $existingCount = $notification?->attachments()->count() ?? 0;
        $deleteCount = count((array) $request->input('delete_attachment_ids', []));
        $newCount = count((array) $request->file('attachments', []));
        if (($existingCount - $deleteCount + $newCount) > 10) {
            abort(422, '添付ファイルは合計10件までです。');
        }

        return $request->validate([
            'notification_master_id' => ['required', 'integer', 'exists:notification_masters,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'notification_status' => ['required', Rule::in(['draft', 'scheduled'])],
            'scheduled_at' => ['nullable', 'date', 'required_if:notification_status,scheduled'],
            'expires_at' => ['nullable', 'date', 'after:scheduled_at'],
            'is_important' => ['nullable', 'boolean'],
            'show_login_modal' => ['nullable', 'boolean'],
            'confirmation_required' => ['nullable', 'boolean'],
            'board_post_id' => ['nullable', 'integer', 'exists:board_posts,id'],
            'link_url' => ['nullable', 'url', 'max:2000'],
            'target_type' => ['required', Rule::in(['all', 'school', 'grade', 'course', 'student'])],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer'],
            'recipient_types' => ['required', 'array', 'min:1'],
            'recipient_types.*' => [Rule::in(['student', 'parent'])],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
            'delete_attachment_ids' => ['nullable', 'array'],
            'delete_attachment_ids.*' => ['integer'],
        ]);
    }

    private function notificationValues(array $validated): array
    {
        return [
            'notification_type_id' => $this->notificationTypeId(),
            'notification_master_id' => $validated['notification_master_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_important' => (bool) ($validated['is_important'] ?? false),
            'show_login_modal' => (bool) ($validated['show_login_modal'] ?? false),
            'confirmation_required' => (bool) ($validated['confirmation_required'] ?? false),
            'board_post_id' => $validated['board_post_id'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'scheduled_at' => $validated['notification_status'] === 'scheduled' ? $validated['scheduled_at'] : null,
            'expires_at' => $validated['expires_at'] ?? null,
            'notification_status' => $validated['notification_status'],
        ];
    }

    private function syncTargets(Notification $notification, array $validated): void
    {
        $notification->targets()->delete();
        $targetIds = $validated['target_type'] === 'all' ? [null] : array_values(array_unique($validated['target_ids'] ?? []));
        foreach ($validated['recipient_types'] as $recipientType) {
            foreach ($targetIds as $targetId) {
                $notification->targets()->create(['target_type' => $validated['target_type'], 'target_id' => $targetId, 'recipient_type' => $recipientType]);
            }
        }
    }

    private function storeAttachments(Request $request, Notification $notification): void
    {
        $start = (int) $notification->attachments()->max('sort_order') + 1;
        foreach ((array) $request->file('attachments', []) as $index => $file) {
            $notification->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'storage_key' => $this->mediaService->store($file, $notification->id),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'sort_order' => $start + $index,
            ]);
        }
    }

    private function deleteSelectedAttachments(Request $request, Notification $notification): void
    {
        $attachments = $notification->attachments()->whereIn('id', (array) $request->input('delete_attachment_ids', []))->get();
        foreach ($attachments as $attachment) {
            $this->mediaService->delete($attachment->storage_key);
            $attachment->delete();
        }
    }


    /**
     * 現在の操作ユーザーIDを返します。
     * ローカル管理画面で認証情報が取得できない場合でも、NOT NULL制約違反を起こさないよう、
     * usersテーブルの先頭ユーザーを操作ユーザーとして使用します。
     */
    private function currentUserId(): int
    {
        $authenticatedUserId = auth()->id();

        if ($authenticatedUserId !== null) {
            return (int) $authenticatedUserId;
        }

        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');

        abort_if($fallbackUserId === null, 422, '操作ユーザーを特定できません。usersテーブルを確認してください。');

        return (int) $fallbackUserId;
    }

    /**
     * 一斉通知で使用する既存の通知種別IDを返します。
     * system_noticeを優先し、存在しなければ有効な通知種別の先頭を使用します。
     */
    private function notificationTypeId(): int
    {
        $notificationTypeId = DB::table('notification_types')
            ->where('code', 'system_notice')
            ->where('is_active', true)
            ->value('id');

        $notificationTypeId ??= DB::table('notification_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        abort_if($notificationTypeId === null, 422, '通知種別が登録されていません。NotificationTypesTableSeederを実行してください。');

        return (int) $notificationTypeId;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(fn ($q) => $q->where('title', 'like', "%{$keyword}%")->orWhere('body', 'like', "%{$keyword}%"));
        }
        if ($request->filled('notification_master_id')) {
            $query->where('notification_master_id', $request->integer('notification_master_id'));
        }
        if ($request->filled('notification_status')) {
            $query->where('notification_status', $request->input('notification_status'));
        }
        if ($request->filled('target_type')) {
            $query->whereHas('targets', fn ($q) => $q->where('target_type', $request->input('target_type')));
        }
        if ($request->filled('scheduled_from')) {
            $query->whereDate('scheduled_at', '>=', $request->input('scheduled_from'));
        }
        if ($request->filled('scheduled_to')) {
            $query->whereDate('scheduled_at', '<=', $request->input('scheduled_to'));
        }
        if ($request->filled('important')) {
            $query->where('is_important', true);
        }
        if ($request->filled('id_min')) {
            $query->where('id', '>=', $request->integer('id_min'));
        }
        if ($request->filled('id_max')) {
            $query->where('id', '<=', $request->integer('id_max'));
        }
        if ($request->filled('title_filter')) {
            $query->where('title', 'like', '%' . trim((string) $request->input('title_filter')) . '%');
        }
        if ($request->filled('master_filter')) {
            $query->whereIn('notification_master_id', array_map('intval', (array) $request->input('master_filter')));
        }
        if ($request->filled('target_filter')) {
            $query->whereHas('targets', fn ($q) => $q->whereIn('target_type', (array) $request->input('target_filter')));
        }
        if ($request->filled('recipient_filter')) {
            $query->whereHas('targets', fn ($q) => $q->whereIn('recipient_type', (array) $request->input('recipient_filter')));
        }
        if ($request->filled('scheduled_from_filter')) {
            $query->whereDate('scheduled_at', '>=', $request->input('scheduled_from_filter'));
        }
        if ($request->filled('scheduled_to_filter')) {
            $query->whereDate('scheduled_at', '<=', $request->input('scheduled_to_filter'));
        }
        if ($request->filled('status_filter')) {
            $query->whereIn('notification_status', (array) $request->input('status_filter'));
        }
        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->input('updated_from'));
        }
        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->input('updated_to'));
        }
    }

    private function targetLabel(Notification $notification): string
    {
        $labels = ['all' => '全体', 'school' => '教室', 'grade' => '学年', 'course' => 'コース', 'student' => '生徒個別'];
        return $labels[$notification->targets->first()?->target_type] ?? '未設定';
    }

    private function recipientLabel(Notification $notification): string
    {
        $types = $notification->targets->pluck('recipient_type')->unique()->values()->all();
        $labels = [];
        if (in_array('student', $types, true)) $labels[] = '生徒';
        if (in_array('parent', $types, true)) $labels[] = '保護者';
        return $labels ? implode('・', $labels) : '未設定';
    }
}
