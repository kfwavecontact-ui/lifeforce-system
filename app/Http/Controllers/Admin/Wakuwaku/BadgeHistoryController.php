<?php

namespace App\Http\Controllers\Admin\Wakuwaku;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\BadgeCategory;
use App\Models\BadgeSeries;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentBadge;
use App\Services\Reward\BadgeGrantService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * わくわく ＞ バッジ ＞ 獲得履歴画面を管理するController。
 *
 * 役割:
 * - 生徒へのバッジ付与単位の履歴一覧、集計、検索、CSV出力
 * - 付与・取り外し・再付与イベントの詳細表示
 *
 * 利用DB:
 * - student_badges（参照）
 * - student_badge_events（参照）
 * - students / schools / badges / badge_categories / badge_series / users（参照）
 */
class BadgeHistoryController extends Controller
{
    public function __construct(private readonly BadgeGrantService $badgeGrantService)
    {
    }

    public function index()
    {
        return view('admin.wakuwaku.badges.history', [
            'badges' => Badge::orderBy('display_order')->orderBy('id')->get(['id', 'name', 'code']),
            'categories' => BadgeCategory::where('is_active', true)->orderBy('display_order')->get(['id', 'name']),
            'series' => BadgeSeries::where('is_active', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name']),
            'schools' => School::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    /**
     * 手動付与モーダル用の生徒Lookup。
     * 生徒コード・氏名で検索し、最大20件だけ返す。
     */
    public function studentLookup(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        if (mb_strlen($keyword) < 1) {
            return response()->json(['items' => []]);
        }

        $students = Student::query()
            ->with('school:id,name')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($keyword) {
                $query->where('student_code', 'like', "%{$keyword}%")
                    ->orWhere('last_name', 'like', "%{$keyword}%")
                    ->orWhere('first_name', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$keyword}%"])
                    ->orWhereRaw("CONCAT(last_name, first_name) LIKE ?", ["%{$keyword}%"]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'items' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'primary' => $student->full_name,
                'secondary' => trim(($student->student_code ?? '') . ' / ' . ($student->school?->name ?? '教室未設定'), ' /'),
            ])->values(),
        ]);
    }

    /**
     * 手動付与モーダル用のバッジLookup。
     * コード・名称・説明で検索し、有効なバッジを最大20件だけ返す。
     * 自動付与専用バッジも、管理者による手動付与候補として表示する。
     */
    public function badgeLookup(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        if ($keyword === '') {
            return response()->json(['items' => []]);
        }

        /*
         * 日本語・全角数字・空白・ハイフンの表記揺れをDB依存の正規表現へ任せず、
         * PHP側で同じ規則に正規化して照合する。
         * バッジ件数が増えても全件取得にならないよう、有効バッジを表示順で最大500件に限定する。
         */
        $normalize = static function (?string $value): string {
            $value = mb_convert_kana((string) $value, 'asKV', 'UTF-8');
            $value = mb_strtolower($value, 'UTF-8');

            return preg_replace('/[\s\-ー―‐‑–—_・]+/u', '', $value) ?? '';
        };

        $normalizedKeyword = $normalize($keyword);

        $badges = Badge::query()
            ->with(['category:id,name', 'series:id,name'])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->filter(function (Badge $badge) use ($normalizedKeyword, $normalize): bool {
                if ($normalizedKeyword === '') {
                    return false;
                }

                foreach ([$badge->code, $badge->name, $badge->description] as $candidate) {
                    if (str_contains($normalize($candidate), $normalizedKeyword)) {
                        return true;
                    }
                }

                return false;
            })
            ->take(20)
            ->values();

        return response()->json([
            'items' => $badges->map(fn (Badge $badge) => [
                'id' => $badge->id,
                'primary' => $badge->name,
                'secondary' => trim(
                    ($badge->code ?? '')
                    . ' / ' . ($badge->category?->name ?? 'カテゴリ未設定')
                    . ' / ' . ($badge->series?->name ?? 'シリーズ未設定'),
                    ' /'
                ),
            ])->values(),
        ]);
    }

    public function list(Request $request): JsonResponse|StreamedResponse
    {
        $query = $this->buildQuery($request);
        $rows = $query->limit(1000)->get();

        if ($request->boolean('export')) {
            return $this->exportCsv($rows, $request);
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return response()->json([
            'rows' => $rows->map(fn (StudentBadge $grant) => $this->serializeGrant($grant))->values(),
            'summary' => [
                'total_grants' => StudentBadge::count(),
                'active_holdings' => StudentBadge::where('status', 'active')->count(),
                'monthly_grants' => StudentBadge::whereBetween('acquired_at', [$monthStart, $monthEnd])->count(),
                'monthly_removals' => StudentBadge::whereBetween('removed_at', [$monthStart, $monthEnd])->count(),
                'monthly_regrants' => StudentBadge::where('grant_method', 'regrant')
                    ->whereBetween('acquired_at', [$monthStart, $monthEnd])
                    ->count(),
            ],
            'result_count' => $rows->count(),
        ]);
    }


    public function grant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'badge_id' => ['required', 'integer', 'exists:badges,id'],
            'acquired_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'notify' => ['nullable', 'boolean'],
            'is_displayed' => ['nullable', 'boolean'],
        ]);

        try {
            $grant = $this->badgeGrantService->grant((int) $validated['student_id'], (int) $validated['badge_id'], [
                'grant_method' => 'manual',
                'operator_id' => auth()->id(),
                'reason' => $validated['reason'],
                'acquired_at' => $validated['acquired_at'] ?? null,
                'notify' => (bool) ($validated['notify'] ?? false),
                'is_displayed' => (bool) ($validated['is_displayed'] ?? true),
                'allow_manual_override' => true,
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'バッジを手動付与しました。', 'grant_id' => $grant->id], 201);
    }

    public function remove(Request $request, StudentBadge $studentBadge): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['required', 'in:mistaken_grant,requirement_not_met,registration_correction,other'],
            'reason_detail' => ['nullable', 'required_if:reason_code,other', 'string', 'max:1000'],
            'removed_at' => ['nullable', 'date'],
            'notify' => ['nullable', 'boolean'],
        ]);

        try {
            $grant = $this->badgeGrantService->remove($studentBadge->id, [
                'operator_id' => auth()->id(),
                'reason_code' => $validated['reason_code'],
                'reason_detail' => $validated['reason_detail'] ?? null,
                'removed_at' => $validated['removed_at'] ?? null,
                'notify' => (bool) ($validated['notify'] ?? false),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'バッジを取り外しました。', 'grant_id' => $grant->id]);
    }

    public function regrant(Request $request, StudentBadge $studentBadge): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'acquired_at' => ['nullable', 'date'],
            'notify' => ['nullable', 'boolean'],
            'is_displayed' => ['nullable', 'boolean'],
        ]);

        try {
            $grant = $this->badgeGrantService->regrant($studentBadge->id, [
                'operator_id' => auth()->id(),
                'reason' => $validated['reason'],
                'acquired_at' => $validated['acquired_at'] ?? null,
                'notify' => (bool) ($validated['notify'] ?? false),
                'is_displayed' => (bool) ($validated['is_displayed'] ?? true),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'バッジを再付与しました。', 'grant_id' => $grant->id], 201);
    }

    public function show(StudentBadge $studentBadge): JsonResponse
    {
        $studentBadge->load([
            'student.school',
            'badge.category',
            'badge.series',
            'grantedBy',
            'removedBy',
            'regrantSource',
            'events.operator',
        ]);

        return response()->json([
            'grant' => $this->serializeGrant($studentBadge),
            'events' => $studentBadge->events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type?->value ?? $event->event_type,
                    'event_type_label' => $this->eventTypeLabel($event->event_type?->value ?? $event->event_type),
                    'operator_name' => $event->operator?->name ?? 'システム',
                    'reason_code' => $event->reason_code?->value ?? $event->reason_code,
                    'reason_detail' => $event->reason_detail,
                    'event_at' => optional($event->event_at)->format('Y/m/d H:i'),
                    'metadata' => $event->metadata ?? [],
                ];
            })->values(),
        ]);
    }

    private function buildQuery(Request $request): Builder
    {
        $query = StudentBadge::query()->with([
            'student.school',
            'badge.category',
            'badge.series',
            'grantedBy',
            'removedBy',
        ]);

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            $query->where(function (Builder $q) use ($keyword) {
                $q->whereHas('student', function (Builder $studentQuery) use ($keyword) {
                    $studentQuery->where('student_code', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('first_name', 'like', "%{$keyword}%")
                        ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$keyword}%"]);
                })->orWhereHas('badge', function (Builder $badgeQuery) use ($keyword) {
                    $badgeQuery->where('code', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%");
                })->orWhere('grant_reason', 'like', "%{$keyword}%")
                    ->orWhere('removal_reason_detail', 'like', "%{$keyword}%");
            });
        }

        $this->applyExactFilter($query, 'student_id', $request->query('student_id'));
        $this->applyExactFilter($query, 'badge_id', $request->query('badge_id'));
        $this->applyExactFilter($query, 'status', $request->query('status'));
        $this->applyExactFilter($query, 'grant_method', $request->query('grant_method'));
        $this->applyExactFilter($query, 'granted_by', $request->query('operator_id'));

        if (($schoolId = $request->query('school_id')) && $schoolId !== 'all') {
            $query->whereHas('student', fn (Builder $q) => $q->where('school_id', $schoolId));
        }
        if (($categoryId = $request->query('category_id')) && $categoryId !== 'all') {
            $query->whereHas('badge', fn (Builder $q) => $q->where('badge_category_id', $categoryId));
        }
        if (($seriesId = $request->query('series_id')) && $seriesId !== 'all') {
            $query->whereHas('badge', fn (Builder $q) => $q->where('badge_series_id', $seriesId));
        }
        if (($dateFrom = $request->query('date_from'))) {
            $query->whereDate('acquired_at', '>=', $dateFrom);
        }
        if (($dateTo = $request->query('date_to'))) {
            $query->whereDate('acquired_at', '<=', $dateTo);
        }

        $sort = (string) $request->query('sort', 'acquired_desc');
        match ($sort) {
            'id_asc' => $query->orderBy('id'),
            'id_desc' => $query->orderByDesc('id'),
            'student_asc' => $query->orderByRaw("(SELECT CONCAT(s.last_name, s.first_name) FROM students s WHERE s.id = student_badges.student_id) ASC NULLS LAST"),
            'student_desc' => $query->orderByRaw("(SELECT CONCAT(s.last_name, s.first_name) FROM students s WHERE s.id = student_badges.student_id) DESC NULLS LAST"),
            'badge_asc' => $query->orderByRaw('(SELECT b.name FROM badges b WHERE b.id = student_badges.badge_id) ASC NULLS LAST'),
            'badge_desc' => $query->orderByRaw('(SELECT b.name FROM badges b WHERE b.id = student_badges.badge_id) DESC NULLS LAST'),
            'category_asc' => $query->orderByRaw('(SELECT bc.name FROM badges b LEFT JOIN badge_categories bc ON bc.id = b.badge_category_id WHERE b.id = student_badges.badge_id) ASC NULLS LAST')->orderByDesc('acquired_at'),
            'category_desc' => $query->orderByRaw('(SELECT bc.name FROM badges b LEFT JOIN badge_categories bc ON bc.id = b.badge_category_id WHERE b.id = student_badges.badge_id) DESC NULLS LAST')->orderByDesc('acquired_at'),
            'series_asc' => $query->orderByRaw('(SELECT bs.name FROM badges b LEFT JOIN badge_series bs ON bs.id = b.badge_series_id WHERE b.id = student_badges.badge_id) ASC NULLS LAST')->orderByDesc('acquired_at'),
            'series_desc' => $query->orderByRaw('(SELECT bs.name FROM badges b LEFT JOIN badge_series bs ON bs.id = b.badge_series_id WHERE b.id = student_badges.badge_id) DESC NULLS LAST')->orderByDesc('acquired_at'),
            'method_asc' => $query->orderBy('grant_method')->orderByDesc('acquired_at'),
            'method_desc' => $query->orderByDesc('grant_method')->orderByDesc('acquired_at'),
            'operator_asc' => $query->orderByRaw('(SELECT u.name FROM users u WHERE u.id = student_badges.granted_by) ASC NULLS FIRST')->orderByDesc('acquired_at'),
            'operator_desc' => $query->orderByRaw('(SELECT u.name FROM users u WHERE u.id = student_badges.granted_by) DESC NULLS LAST')->orderByDesc('acquired_at'),
            'status_asc' => $query->orderBy('status')->orderByDesc('acquired_at'),
            'status_desc' => $query->orderByDesc('status')->orderByDesc('acquired_at'),
            'acquired_asc' => $query->orderBy('acquired_at')->orderBy('id'),
            default => $query->orderByDesc('acquired_at')->orderByDesc('id'),
        };

        return $query;
    }

    private function applyExactFilter(Builder $query, string $column, mixed $value): void
    {
        if ($value !== null && $value !== '' && $value !== 'all') {
            $query->where($column, $value);
        }
    }

    private function serializeGrant(StudentBadge $grant): array
    {
        $status = $grant->status?->value ?? $grant->status;
        $grantMethod = $grant->grant_method?->value ?? $grant->grant_method;

        return [
            'id' => $grant->id,
            'student_id' => $grant->student_id,
            'student_code' => $grant->student?->student_code,
            'student_name' => $grant->student?->full_name ?? '不明な生徒',
            'school_name' => $grant->student?->school?->name ?? '未所属',
            'badge_id' => $grant->badge_id,
            'badge_code' => $grant->badge?->code,
            'badge_name' => $grant->badge?->name ?? '削除済みバッジ',
            'badge_image_path' => $this->resolveImageUrl($grant->badge?->image_path),
            'category_name' => $grant->badge?->category?->name ?? '未設定',
            'series_name' => $grant->badge?->series?->name ?? '未設定',
            'status' => $status,
            'status_label' => $status === 'active' ? '付与中' : '取り外し済み',
            'grant_method' => $grantMethod,
            'grant_method_label' => $this->grantMethodLabel($grantMethod),
            'granted_by_name' => $grant->grantedBy?->name ?? 'システム',
            'removed_by_name' => $grant->removedBy?->name,
            'grant_reason' => $grant->grant_reason,
            'removal_reason_code' => $grant->removal_reason_code?->value ?? $grant->removal_reason_code,
            'removal_reason_detail' => $grant->removal_reason_detail,
            'acquired_at' => optional($grant->acquired_at)->format('Y/m/d H:i'),
            'removed_at' => optional($grant->removed_at)->format('Y/m/d H:i'),
            'regrant_source_id' => $grant->regrant_source_id,
            'grant_notification_sent' => (bool) $grant->grant_notification_sent,
            'removal_notification_sent' => (bool) $grant->removal_notification_sent,
            'is_displayed' => (bool) $grant->is_displayed,
        ];
    }


    private function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (Storage::disk('s3')->exists($normalized)) {
            return Storage::disk('s3')->temporaryUrl($normalized, now()->addMinutes(30));
        }

        if (str_starts_with($normalized, 'storage/')) {
            $publicRelative = $normalized;
        } else {
            $publicRelative = 'storage/' . $normalized;
        }

        if (file_exists(public_path($publicRelative))) {
            return asset($publicRelative);
        }

        if (file_exists(public_path($normalized))) {
            return asset($normalized);
        }

        return null;
    }

    private function grantMethodLabel(?string $method): string
    {
        return match ($method) {
            'auto' => '自動',
            'manual' => '手動',
            'regrant' => '再付与',
            default => '不明',
        };
    }

    private function eventTypeLabel(?string $type): string
    {
        return match ($type) {
            'granted' => '付与',
            'removed' => '取り外し',
            'regranted' => '再付与',
            default => '更新',
        };
    }

    private function exportCsv($rows, Request $request): StreamedResponse
    {
        $filename = 'badge-acquisition-history-' . now()->format('Ymd-His') . '.csv';
        $available = [
            'id' => 'ID', 'acquired_at' => '獲得日時', 'student_code' => '生徒コード',
            'student_name' => '生徒名', 'school_name' => '教室', 'badge_code' => 'バッジコード',
            'badge_name' => 'バッジ名', 'category_name' => 'カテゴリ', 'series_name' => 'シリーズ',
            'grant_method_label' => '付与方法', 'granted_by_name' => '付与者', 'status_label' => '状態',
            'removed_at' => '取り外し日時', 'removed_by_name' => '取り外し担当', 'reason' => '理由',
            'is_displayed' => '生徒画面表示', 'grant_notification_sent' => '付与通知',
            'removal_notification_sent' => '取り外し通知',
        ];
        $requested = array_values(array_filter(explode(',', (string) $request->query('csv_columns', ''))));
        $columns = $requested ? array_intersect_key($available, array_flip($requested)) : $available;

        return response()->streamDownload(function () use ($rows, $columns) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values($columns));
            foreach ($rows as $grant) {
                $row = $this->serializeGrant($grant);
                $row['reason'] = $row['removal_reason_detail'] ?: $row['grant_reason'];
                $row['is_displayed'] = $row['is_displayed'] ? '表示' : '非表示';
                $row['grant_notification_sent'] = $row['grant_notification_sent'] ? '送信済み' : '未送信';
                $row['removal_notification_sent'] = $row['removal_notification_sent'] ? '送信済み' : '未送信';
                fputcsv($handle, array_map(fn ($key) => $row[$key] ?? '', array_keys($columns)));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
