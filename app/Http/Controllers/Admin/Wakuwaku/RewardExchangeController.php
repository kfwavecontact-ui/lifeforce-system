<?php

namespace App\Http\Controllers\Admin\Wakuwaku;

use App\Enums\RewardExchangeStatus;
use App\Http\Controllers\Controller;
use App\Models\RewardCategory;
use App\Models\RewardExchangeRequest;
use App\Models\RewardItem;
use App\Models\Student;
use App\Services\Reward\RewardExchangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ファイル概要: 商品交換所3画面の表示・検索・操作受付を担当します。
 * 役割: 商品一覧、申請情報、交換履歴の検索条件を組み立て、業務更新をServiceへ委譲します。
 * 関連画面: わくわく ＞ 商品交換所 ＞ 商品一覧／申請情報／交換履歴
 * 関連Service: App\Services\Reward\RewardExchangeService
 * 利用DB: reward_items / reward_categories / reward_exchange_requests / reward_exchange_events /
 *         students / student_point_balances / point_transactions / account_transactions
 * 利用理由: 商品表示、交換可否、申請管理、履歴・連携情報の追跡に使用します。
 * 参照/更新区分: 画面表示は参照、申請・状態変更はService経由で更新します。
 */
class RewardExchangeController extends Controller
{
    public function __construct(private readonly RewardExchangeService $service)
    {
    }

    /** 商品一覧と、選択生徒の残高・交換可否を表示します。 */
    public function products(Request $request): View
    {
        $student = $request->filled('student_id')
            ? Student::with(['pointBalance', 'school'])->find($request->integer('student_id'))
            : null;

        $query = RewardItem::query()
            ->with(['category', 'stock', 'mainImage'])
            ->withSum([
                'exchangeRequests as delivered_count' => fn ($q) => $q->where('status', RewardExchangeStatus::Delivered->value),
            ], 'quantity')
            ->where('is_active', true);

        if ($keyword = trim((string) $request->input('keyword'))) {
            $query->where(fn ($q) => $q
                ->where('code', 'ilike', "%{$keyword}%")
                ->orWhere('name', 'ilike', "%{$keyword}%")
                ->orWhere('description', 'ilike', "%{$keyword}%"));
        }
        if ($request->filled('category_id')) {
            $query->where('reward_category_id', $request->integer('category_id'));
        }
        if ($request->filled('min_points')) {
            $query->where('required_points', '>=', $request->integer('min_points'));
        }
        if ($request->filled('max_points')) {
            $query->where('required_points', '<=', $request->integer('max_points'));
        }
        if ($request->boolean('recommended')) {
            $query->where('is_recommended', true);
        }
        if ($request->boolean('limited')) {
            $query->where('is_limited', true);
        }
        if ($request->boolean('in_stock')) {
            $query->where(fn ($q) => $q
                ->where('is_stock_managed', false)
                ->orWhereHas('stock', fn ($stock) => $stock->whereRaw('stock_quantity - reserved_quantity > 0')));
        }

        $items = $query->orderBy('sort_order')->orderBy('id')->paginate(12)->withQueryString();

        // 人気順位は受渡完了した申請行数ではなく、実際に受け渡した商品数量の合計で算出します。
        $ranking = RewardExchangeRequest::query()
            ->where('status', RewardExchangeStatus::Delivered->value)
            ->select('reward_item_id', DB::raw('COALESCE(SUM(quantity), 0) AS total'))
            ->groupBy('reward_item_id')
            ->orderByDesc('total')
            ->orderBy('reward_item_id')
            ->get();
        $rankMap = [];
        $previousTotal = null;
        $rank = 0;
        foreach ($ranking as $index => $row) {
            if ($previousTotal !== (int) $row->total) {
                $rank = $index + 1;
                $previousTotal = (int) $row->total;
            }
            if ($rank <= 3) {
                $rankMap[(int) $row->reward_item_id] = $rank;
            }
        }
        $items->getCollection()->transform(function (RewardItem $item) use ($rankMap): RewardItem {
            $item->rank = $rankMap[$item->id] ?? null;
            return $item;
        });

        $open = $student
            ? RewardExchangeRequest::query()
                ->where('student_id', $student->id)
                ->whereIn('status', $this->openStatuses())
                ->pluck('reward_item_id')
                ->all()
            : [];

        return view('admin.wakuwaku.reward-exchange.products', [
            'items' => $items,
            'student' => $student,
            'open' => $open,
            'categories' => RewardCategory::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /** 生徒検索候補をJSONで返します。 */
    public function studentLookup(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->input('q'));
        if ($keyword === '') {
            return response()->json([]);
        }

        $students = Student::with('pointBalance')
            ->where(fn ($q) => $q
                ->where('student_code', 'ilike', "%{$keyword}%")
                ->orWhere('last_name', 'ilike', "%{$keyword}%")
                ->orWhere('first_name', 'ilike', "%{$keyword}%"))
            ->orderBy('student_code')
            ->limit(20)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'label' => $student->student_code.'｜'.$student->last_name.' '.$student->first_name,
                'points' => (int) ($student->pointBalance->current_points ?? 0),
            ]);

        return response()->json($students);
    }

    /** 交換申請を登録し、Ajax時は更新後の残高・在庫を返します。 */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'reward_item_id' => ['required', 'exists:reward_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'delivery_method' => ['required', Rule::in(['classroom', 'shipping'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $exchange = $this->service->request(
                (int) $data['student_id'],
                (int) $data['reward_item_id'],
                (int) $data['quantity'],
                $data['delivery_method'],
                $data['note'] ?? null,
                auth()->id() ?? 1
            );

            if ($request->expectsJson()) {
                $exchange->load(['student.pointBalance', 'item.stock']);
                return response()->json([
                    'message' => '交換申請を登録しました。',
                    'request_number' => $exchange->request_number,
                    'student_points' => (int) ($exchange->student?->pointBalance?->current_points ?? 0),
                    'item_id' => (int) $exchange->reward_item_id,
                    'available_quantity' => $exchange->item?->is_stock_managed
                        ? (int) ($exchange->item?->stock?->available_quantity ?? 0)
                        : null,
                ], 201);
            }

            return back()->with('success', '交換申請を登録しました。');
        } catch (\Throwable $e) {
            Log::error('商品交換申請の登録に失敗しました。', [
                'student_id' => $data['student_id'] ?? null,
                'reward_item_id' => $data['reward_item_id'] ?? null,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /** 未完了申請を表示します。 */
    public function applications(Request $request): View
    {
        $perPage = $this->perPage($request, 30);
        $rows = $this->requestQuery($request)
            ->whereIn('reward_exchange_requests.status', $this->openStatuses())
            ->orderByDesc('requested_at')
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'requested' => RewardExchangeRequest::where('status', RewardExchangeStatus::Requested->value)->count(),
            'approved' => RewardExchangeRequest::where('status', RewardExchangeStatus::Approved->value)->count(),
            'preparing' => RewardExchangeRequest::where('status', RewardExchangeStatus::Preparing->value)->count(),
            'shipped' => RewardExchangeRequest::where('status', RewardExchangeStatus::Shipped->value)->count(),
        ];

        return view('admin.wakuwaku.reward-exchange.applications', [
            'rows' => $rows,
            'summary' => $summary,
            'statuses' => RewardExchangeStatus::labels(),
            'perPage' => $perPage,
        ]);
    }

    /** 完了・却下・取消のみを交換履歴として表示します。 */
    public function history(Request $request): View
    {
        $perPage = $this->perPage($request, 30);
        $terminalStatuses = [
            RewardExchangeStatus::Delivered->value,
            RewardExchangeStatus::Rejected->value,
            RewardExchangeStatus::Cancelled->value,
        ];
        $rows = $this->requestQuery($request)
            ->whereIn('reward_exchange_requests.status', $terminalStatuses)
            ->orderByDesc(DB::raw('COALESCE(delivered_at, rejected_at, cancelled_at, requested_at)'))
            ->paginate($perPage)
            ->withQueryString();

        $base = RewardExchangeRequest::query()->whereIn('status', $terminalStatuses);
        $summary = [
            'total' => (clone $base)->count(),
            'approved' => (clone $base)->whereNotNull('approved_at')->count(),
            'delivered' => (clone $base)->where('status', RewardExchangeStatus::Delivered->value)->count(),
            'rejected' => (clone $base)->where('status', RewardExchangeStatus::Rejected->value)->count(),
            'cancelled' => (clone $base)->where('status', RewardExchangeStatus::Cancelled->value)->count(),
            'used' => (clone $base)->sum('request_points'),
            'returned' => (clone $base)->sum('returned_points'),
            'cost' => DB::table('reward_exchange_requests as r')
                ->join('reward_items as i', 'i.id', '=', 'r.reward_item_id')
                ->where('r.status', RewardExchangeStatus::Delivered->value)
                ->sum(DB::raw('i.cost_price * r.quantity')),
        ];

        return view('admin.wakuwaku.reward-exchange.history', [
            'rows' => $rows,
            'summary' => $summary,
            'statuses' => collect(RewardExchangeStatus::labels())->only($terminalStatuses)->all(),
            'categories' => RewardCategory::orderBy('sort_order')->get(),
            'perPage' => $perPage,
        ]);
    }

    /** 状態遷移をServiceへ委譲します。 */
    public function transition(Request $request, RewardExchangeRequest $exchange): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RewardExchangeStatus::labels()))],
            'delivery_method' => ['nullable', Rule::in(['classroom', 'shipping'])],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->transition(
            $exchange,
            RewardExchangeStatus::from($data['status']),
            $data,
            auth()->id() ?? 1
        );

        return back()->with('success', '申請状態を更新しました。');
    }

    /** 申請詳細と操作タイムラインを返します。 */
    public function show(RewardExchangeRequest $exchange): JsonResponse
    {
        $exchange->load(['student.school', 'item.category', 'item.mainImage', 'handler', 'events.actor']);

        $events = $exchange->events->map(fn ($event) => [
            'at' => $event->occurred_at,
            'label' => $event->title,
            'detail' => $event->detail,
            'actor' => $event->actor?->name ?? 'システム',
            'type' => $event->event_type,
        ])->values();

        // 新しい履歴テーブル導入前の申請も表示できるよう、履歴が空の場合は既存日時から補完します。
        if ($events->isEmpty()) {
            $events = collect([
                ['at' => $exchange->requested_at, 'label' => '申請', 'detail' => 'ポイント減算・在庫予約', 'actor' => $exchange->handler?->name ?? 'システム', 'type' => 'requested'],
                ['at' => $exchange->approved_at, 'label' => '承認', 'detail' => '申請を承認', 'actor' => $exchange->handler?->name ?? 'システム', 'type' => 'approved'],
                ['at' => $exchange->prepared_at, 'label' => '準備', 'detail' => '受渡準備中', 'actor' => $exchange->handler?->name ?? 'システム', 'type' => 'preparing'],
                ['at' => $exchange->shipped_at, 'label' => '発送', 'detail' => $exchange->tracking_number, 'actor' => $exchange->handler?->name ?? 'システム', 'type' => 'shipped'],
                ['at' => $exchange->delivered_at, 'label' => '受渡完了', 'detail' => '在庫確定・会計連携', 'actor' => $exchange->handler?->name ?? 'システム', 'type' => 'delivered'],
                ['at' => $exchange->rejected_at, 'label' => '却下', 'detail' => $exchange->rejection_reason, 'actor' => $exchange->handler?->name ?? 'システム', 'type' => 'rejected'],
                ['at' => $exchange->cancelled_at, 'label' => '取消', 'detail' => $exchange->cancellation_reason, 'actor' => $exchange->handler?->name ?? 'システム', 'type' => 'cancelled'],
            ])->filter(fn ($event) => $event['at'])->sortBy('at')->values();
        }

        return response()->json([
            'exchange' => $exchange,
            'events' => $events,
            'links' => [
                'point' => $exchange->point_transaction_id
                    ? route('admin.wakuwaku.points.history', ['student_id' => $exchange->student_id])
                    : null,
                'account' => $exchange->account_transaction_id
                    ? route('admin.operations.classroom-accounting.transactions.index', ['source_id' => $exchange->account_transaction_id])
                    : null,
            ],
        ]);
    }

    /** 共通検索条件を適用します。 */
    private function requestQuery(Request $request)
    {
        $query = RewardExchangeRequest::query()->with(['student.school', 'item.category', 'item.mainImage', 'handler']);

        if ($keyword = trim((string) $request->input('keyword'))) {
            $query->where(fn ($q) => $q
                ->where('request_number', 'ilike', "%{$keyword}%")
                ->orWhereHas('student', fn ($student) => $student
                    ->where('last_name', 'ilike', "%{$keyword}%")
                    ->orWhere('first_name', 'ilike', "%{$keyword}%"))
                ->orWhereHas('item', fn ($item) => $item
                    ->where('name', 'ilike', "%{$keyword}%")
                    ->orWhere('code', 'ilike', "%{$keyword}%")));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('delivery_method')) {
            $query->where('delivery_method', $request->input('delivery_method'));
        }
        if ($request->filled('category_id')) {
            $query->whereHas('item', fn ($item) => $item->where('reward_category_id', $request->integer('category_id')));
        }
        if ($request->filled('from')) {
            $query->whereDate('requested_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('requested_at', '<=', $request->input('to'));
        }

        return $query;
    }

    private function openStatuses(): array
    {
        return [
            RewardExchangeStatus::Requested->value,
            RewardExchangeStatus::Approved->value,
            RewardExchangeStatus::Preparing->value,
            RewardExchangeStatus::Shipped->value,
        ];
    }

    private function perPage(Request $request, int $default): int
    {
        $value = $request->integer('per_page', $default);
        return in_array($value, [20, 30, 50], true) ? $value : $default;
    }
}
