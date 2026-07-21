<?php

namespace App\Http\Controllers\Admin\Wakuwaku;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ポイント残高一覧画面を管理するController。
 *
 * 関連画面:
 * - わくわく ＞ ポイント ＞ ポイント残高
 *
 * 利用DBテーブル:
 * - students: 生徒コード・氏名・学年・在籍状態の取得（参照）
 * - student_point_balances: 現在ポイントの取得（参照）
 * - point_transactions: 今月獲得・今月利用・最終変動日の取得（参照）
 * - grades: 学年名称と検索候補の取得（参照）
 * - enrollment_statuses: 在籍状態名称と検索候補の取得（参照）
 * - student_classes / classrooms: 現在所属教室の取得（参照）
 *
 * この画面ではポイント残高・履歴を更新しない。
 */
class PointBalanceController extends Controller
{
    /**
     * ポイント残高一覧を表示する。
     */
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $query = $this->buildBalanceQuery();
        $this->applyFilters($query, $filters);

        // 検索条件に連動したサマリーを、ページネーション前の対象全件から算出する。
        $summarySource = DB::query()->fromSub(clone $query, 'point_balance_summary');
        $summary = [
            'total_points' => (int) (clone $summarySource)->sum('current_points'),
            'month_earned' => (int) (clone $summarySource)->sum('month_earned'),
            'month_used' => (int) (clone $summarySource)->sum('month_used'),
            'managed_student_count' => (int) (clone $summarySource)->count(),
        ];

        [$sort, $direction] = $this->resolveSort($filters);
        $this->applySort($query, $sort, $direction);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $balances = $query->paginate($perPage)->withQueryString();

        return view('admin.wakuwaku.points.index', [
            'balances' => $balances,
            'summary' => $summary,
            'grades' => DB::table('grades')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'enrollmentStatuses' => DB::table('enrollment_statuses')->where('is_active', true)->orderBy('sort_order')->get(['id', 'status']),
            'classrooms' => DB::table('classrooms')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * 一覧表示に必要な集計済みQueryを組み立てる。
     */
    private function buildBalanceQuery(): Builder
    {
        $monthStart = now()->startOfMonth()->toDateTimeString();
        $monthEnd = now()->endOfMonth()->toDateTimeString();

        // 今月の獲得・利用ポイントを生徒単位で集計する。
        $monthlyTransactions = DB::table('point_transactions')
            ->select('student_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) AS month_earned')
            ->selectRaw('COALESCE(SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END), 0) AS month_used')
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->groupBy('student_id');

        // 全履歴から最後にポイントが変動した日時を取得する。
        $latestTransactions = DB::table('point_transactions')
            ->select('student_id')
            ->selectRaw('MAX(occurred_at) AS last_occurred_at')
            ->groupBy('student_id');

        // 有効な所属教室を生徒単位で集約する。複数所属はカンマ区切りで表示する。
        $activeClassrooms = DB::table('student_classes as sc')
            ->join('classrooms as c', 'c.id', '=', 'sc.classroom_id')
            ->select('sc.student_id')
            ->selectRaw("STRING_AGG(DISTINCT c.name, '、' ORDER BY c.name) AS classroom_names")
            ->selectRaw('MIN(c.id) AS classroom_filter_id')
            ->where('sc.is_active', true)
            ->where('c.is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('sc.started_at')->orWhereDate('sc.started_at', '<=', today());
            })
            ->where(function (Builder $query) {
                $query->whereNull('sc.ended_at')->orWhereDate('sc.ended_at', '>=', today());
            })
            ->groupBy('sc.student_id');

        return DB::table('students as s')
            ->leftJoin('student_point_balances as spb', 'spb.student_id', '=', 's.id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('enrollment_statuses as es', 'es.id', '=', 's.enrollment_status_id')
            ->leftJoinSub($monthlyTransactions, 'monthly_pt', fn ($join) => $join->on('monthly_pt.student_id', '=', 's.id'))
            ->leftJoinSub($latestTransactions, 'latest_pt', fn ($join) => $join->on('latest_pt.student_id', '=', 's.id'))
            ->leftJoinSub($activeClassrooms, 'active_classrooms', fn ($join) => $join->on('active_classrooms.student_id', '=', 's.id'))
            ->select([
                's.id',
                's.student_code',
                's.last_name',
                's.first_name',
                's.last_name_kana',
                's.first_name_kana',
                's.grade_id',
                's.enrollment_status_id',
                's.is_active',
                'g.name as grade_name',
                'g.sort_order as grade_sort_order',
                'es.status as enrollment_status_name',
                'active_classrooms.classroom_names',
                'active_classrooms.classroom_filter_id',
                'latest_pt.last_occurred_at',
            ])
            ->selectRaw('COALESCE(spb.current_points, 0) AS current_points')
            ->selectRaw('COALESCE(monthly_pt.month_earned, 0) AS month_earned')
            ->selectRaw('COALESCE(monthly_pt.month_used, 0) AS month_used');
    }

    /**
     * GETパラメータを画面で許可する値だけに絞る。
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'classroom_id' => ['nullable', 'integer'],
            'grade_id' => ['nullable', 'integer'],
            'enrollment_status_id' => ['nullable', 'integer'],
            'min_points' => ['nullable', 'integer'],
            'max_points' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:student_code,name,grade,current_points,month_earned,month_used,last_occurred_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
        ]);
    }

    /**
     * 検索条件を一覧Queryへ適用する。
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where(function (Builder $subQuery) use ($keyword) {
                $subQuery->where('s.student_code', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.last_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.first_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.last_name_kana', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.first_name_kana', 'ILIKE', "%{$keyword}%");
            });
        }

        if (! empty($filters['classroom_id'])) {
            // 複数教室所属にも対応するため、所属テーブルの存在条件で絞り込む。
            $query->whereExists(function (Builder $subQuery) use ($filters) {
                $subQuery->selectRaw('1')
                    ->from('student_classes as filter_sc')
                    ->whereColumn('filter_sc.student_id', 's.id')
                    ->where('filter_sc.classroom_id', (int) $filters['classroom_id'])
                    ->where('filter_sc.is_active', true)
                    ->where(function (Builder $dateQuery) {
                        $dateQuery->whereNull('filter_sc.started_at')->orWhereDate('filter_sc.started_at', '<=', today());
                    })
                    ->where(function (Builder $dateQuery) {
                        $dateQuery->whereNull('filter_sc.ended_at')->orWhereDate('filter_sc.ended_at', '>=', today());
                    });
            });
        }

        if (! empty($filters['grade_id'])) {
            $query->where('s.grade_id', (int) $filters['grade_id']);
        }

        if (! empty($filters['enrollment_status_id'])) {
            $query->where('s.enrollment_status_id', (int) $filters['enrollment_status_id']);
        }

        if (array_key_exists('min_points', $filters) && $filters['min_points'] !== null && $filters['min_points'] !== '') {
            $query->whereRaw('COALESCE(spb.current_points, 0) >= ?', [(int) $filters['min_points']]);
        }

        if (array_key_exists('max_points', $filters) && $filters['max_points'] !== null && $filters['max_points'] !== '') {
            $query->whereRaw('COALESCE(spb.current_points, 0) <= ?', [(int) $filters['max_points']]);
        }
    }

    /**
     * ソート指定を安全な初期値へ正規化する。
     */
    private function resolveSort(array $filters): array
    {
        return [
            $filters['sort'] ?? 'student_code',
            $filters['direction'] ?? 'asc',
        ];
    }

    /**
     * 許可済みのソート条件を一覧Queryへ適用する。
     */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'name' => $query->orderBy('s.last_name_kana', $direction)->orderBy('s.first_name_kana', $direction),
            'grade' => $query->orderByRaw('g.sort_order IS NULL')->orderBy('g.sort_order', $direction),
            'current_points' => $query->orderByRaw('COALESCE(spb.current_points, 0) ' . $direction),
            'month_earned' => $query->orderByRaw('COALESCE(monthly_pt.month_earned, 0) ' . $direction),
            'month_used' => $query->orderByRaw('COALESCE(monthly_pt.month_used, 0) ' . $direction),
            'last_occurred_at' => $query->orderByRaw('latest_pt.last_occurred_at IS NULL')->orderBy('latest_pt.last_occurred_at', $direction),
            default => $query->orderBy('s.student_code', $direction),
        };

        $query->orderBy('s.id');
    }
}
