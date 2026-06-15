<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\NotificationHistory;
use Illuminate\Http\JsonResponse;

class MasterController extends Controller
{
    private array $masters = [
        'enrollment_statuses' => ['title' => '入会状態', 'description' => '生徒の入会・休会・退会などの状態を管理します。'],
        'employment_types' => ['title' => '雇用形態', 'description' => '講師やスタッフの雇用形態を管理します。'],
        'teacher_statuses' => ['title' => '講師状態', 'description' => '講師の在籍・休職・退職などの状態を管理します。'],
        'grades' => ['title' => '学年', 'description' => '年少・年中・小学生などの学年区分を管理します。'],

        'lesson_types' => ['title' => '授業種別', 'description' => '脳開発・将棋・資格学習などの授業種別を管理します。'],
        'learning_plan_types' => ['title' => '学習計画種別', 'description' => '計画学習の種類を管理します。'],
        'learning_material_categories' => ['title' => '教材カテゴリ', 'description' => '教材や資料を分類するカテゴリを管理します。'],
        'routine_completion_types' => ['title' => '達成判定種別', 'description' => '時間・回数・正答率などの達成条件を管理します。'],
        'qualifications' => ['title' => '資格種別', 'description' => '英検・漢検などの資格を管理します。'],

        'note_types' => ['title' => 'メモ種別', 'description' => 'メモ種別を管理します。'],
        'contact_types' => ['title' => '連絡種別', 'description' => '電話・メール・LINE・面談などを管理します。'],
        'contact_statuses' => ['title' => '対応状況', 'description' => '未対応・対応中・完了などを管理します。'],
        'notification_masters' => ['title' => '通知種類', 'description' => '通知センターで扱う通知の種類を管理します。'],

        'payment_methods' => ['title' => '支払方法', 'description' => '口座振替・現金・クレジットなどを管理します。'],
        'discounts' => ['title' => '割引種別', 'description' => '兄弟割・紹介割などを管理します。'],

        'event_categories' => ['title' => 'イベントカテゴリ', 'description' => 'イベントカテゴリを管理します。'],
        'event_statuses' => ['title' => 'イベント状態', 'description' => '募集中・受付終了・開催済みなどを管理します。'],
        'event_rewards' => ['title' => 'イベント報酬', 'description' => 'イベント参加時の報酬を管理します。'],

        'badge_categories' => ['title' => 'バッジカテゴリ', 'description' => 'バッジカテゴリを管理します。'],
        'reward_categories' => ['title' => '景品カテゴリ', 'description' => 'ポイント交換景品のカテゴリを管理します。'],
        'shop_categories' => ['title' => '商品カテゴリ', 'description' => 'ショップ商品のカテゴリを管理します。'],
    ];

    private array $systemColumns = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function index()
    {
        return view('admin.system.master', [
            'masters' => $this->masters,
        ]);
    }

    public function list(Request $request)
    {
        $table = $this->resolveTable($request->string('master')->toString());

        $columns = $this->columns($table);

        $orderColumn = $this->defaultOrderColumn($table);

        $rows = DB::table($table)
            ->orderBy($orderColumn)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values();

        return response()->json([
            'meta' => $this->masters[$table],
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $table = $this->resolveTable($request->string('master')->toString());

        $payload = $this->buildPayload($request, $table);

        if (Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = now();
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $id = DB::table($table)->insertGetId($payload);

        return response()->json([
            'message' => 'マスタ値を追加しました。',
            'row' => (array) DB::table($table)->where('id', $id)->first(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $table = $this->resolveTable($request->string('master')->toString());

        $payload = $this->buildPayload($request, $table);

        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table($table)->where('id', $id)->update($payload);

        return response()->json([
            'message' => 'マスタ値を更新しました。',
            'row' => (array) DB::table($table)->where('id', $id)->first(),
        ]);
    }

    public function reorder(Request $request)
    {
        $table = $this->resolveTable($request->string('master')->toString());

        $orderColumn = null;

        if (Schema::hasColumn($table, 'sort_order')) {
            $orderColumn = 'sort_order';
        } elseif (Schema::hasColumn($table, 'display_order')) {
            $orderColumn = 'display_order';
        }

        if (!$orderColumn) {
            abort(422, 'このマスタは表示順変更に対応していません。');
        }

        $items = $request->input('items', []);

        DB::transaction(function () use ($table, $orderColumn, $items) {
            foreach ($items as $item) {
                DB::table($table)
                    ->where('id', $item['id'])
                    ->update([
                        $orderColumn => $item['sort_order'],
                        'updated_at' => Schema::hasColumn($table, 'updated_at') ? now() : DB::raw('updated_at'),
                    ]);
            }
        });

        return response()->json([
            'message' => '表示順を更新しました。',
        ]);
    }

    private function resolveTable(string $table): string
    {
        if (!array_key_exists($table, $this->masters)) {
            abort(404, '対象マスタが見つかりません。');
        }

        if (!Schema::hasTable($table)) {
            abort(404, "テーブル {$table} が存在しません。");
        }

        return $table;
    }

    private function columns(string $table): array
    {
        return collect(Schema::getColumns($table))
            ->map(function (array $column) {
                $name = $column['name'];

                return [
                    'name' => $name,
                    'label' => $this->columnLabel($name),
                    'type' => $column['type_name'] ?? $column['type'] ?? 'string',
                    'nullable' => (bool) ($column['nullable'] ?? true),
                    'required' => !$this->isSystemColumn($name) && !($column['nullable'] ?? true),
                    'editable' => !$this->isSystemColumn($name),
                    'system' => $this->isSystemColumn($name),
                ];
            })
            ->values()
            ->all();
    }

    private function buildPayload(Request $request, string $table): array
    {
        $columns = $this->columns($table);
        $data = $request->input('data', []);

        $payload = [];

        foreach ($columns as $column) {
            $name = $column['name'];

            if (!$column['editable']) {
                continue;
            }

            if (!array_key_exists($name, $data)) {
                continue;
            }

            $value = $data[$name];

            if ($column['required'] && ($value === null || $value === '')) {
                abort(422, "{$column['label']}は必須です。");
            }

            $payload[$name] = $this->castValue($value, $column['type']);
        }

        return $payload;
    }

    private function castValue(mixed $value, string $type): mixed
    {
        if ($value === '') {
            return null;
        }

        if (str_contains($type, 'bool')) {
            return (bool) $value;
        }

        if (
            str_contains($type, 'int') ||
            str_contains($type, 'bigint') ||
            str_contains($type, 'smallint')
        ) {
            return $value === null ? null : (int) $value;
        }

        if (
            str_contains($type, 'numeric') ||
            str_contains($type, 'decimal') ||
            str_contains($type, 'float') ||
            str_contains($type, 'double')
        ) {
            return $value === null ? null : (float) $value;
        }

        return $value;
    }

    private function defaultOrderColumn(string $table): string
    {
        foreach (['sort_order', 'display_order', 'order', 'id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return 'id';
    }

    private function isSystemColumn(string $column): bool
    {
        return in_array($column, $this->systemColumns, true);
    }

    private function columnLabel(string $column): string
    {
        return [
            'id' => 'ID',
            'code' => 'コード',
            'name' => '名称',
            'display_name' => '表示名',
            'description' => '説明',
            'sort_order' => '表示順',
            'display_order' => '表示順',
            'is_active' => '有効',
            'created_at' => '作成日時',
            'updated_at' => '更新日時',
        ][$column] ?? $column;
    }

    public function notificationMasters()
    {
        return view('admin.system.master', [
            'masters' => [
                'notification_masters' => [
                    'title' => '通知種類',
                    'description' => '通知センターで扱う通知の種類を管理します。',
                ],
            ],
            'initialMaster' => 'notification_masters',
            'pageTitle' => '通知マスタ',
            'pageDescription' => '通知センターで扱う通知の種類を管理します。',
        ]);
    }

    public function roleNotificationSettings()
    {
        $roles = [
            'admin' => '管理者',
            'teacher' => '講師',
            'student' => '生徒',
            'parent' => '保護者',
        ];

        $notifications = DB::table('notification_masters')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $settings = DB::table('role_notification_settings')
            ->get()
            ->groupBy('notification_master_id');

        return view(
            'admin.system.role_notification_settings',
            compact(
                'roles',
                'notifications',
                'settings'
            )
        );
    }

    public function updateRoleNotificationSettings(Request $request): JsonResponse
    {
        $items = $request->input('items', []);

        DB::transaction(function () use ($items) {

            foreach ($items as $item) {

                DB::table('role_notification_settings')
                    ->where('id', $item['id'])
                    ->update([
                        'portal_enabled' => (bool) ($item['portal_enabled'] ?? false),
                        'email_enabled' => (bool) ($item['email_enabled'] ?? false),
                        'line_enabled' => (bool) ($item['line_enabled'] ?? false),
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json([
            'message' => '保存しました。',
        ]);
    }

    public function notificationHistories(Request $request)
    {
        $query = NotificationHistory::query()
            ->with('notificationMaster');
        
        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        if ($request->filled('category')) {
            $query->whereHas('notificationMaster', function ($q) use ($request) {
                $q->where('category', $request->category);
            });
        }

        if ($request->filled('notification_master_id')) {
            $query->where('notification_master_id', $request->notification_master_id);
        }

        if ($request->filled('recipient_role')) {
            $query->where('recipient_role', $request->recipient_role);
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('sender_name', 'like', "%{$keyword}%")
                    ->orWhere('recipient_name', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%");
            });
        }

        $histories = $query
            ->latest('sent_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $notificationMasters = DB::table('notification_masters')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $categories = $notificationMasters
            ->pluck('category')
            ->unique()
            ->values();

        return view(
            'admin.system.notification_histories',
            compact(
                'histories',
                'notificationMasters',
                'categories'
            )
        );
    }
}