<?php

namespace App\Http\Controllers\Admin\Wakuwaku;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ポイント履歴一覧画面を管理するController。
 *
 * 関連画面:
 * - わくわく ＞ ポイント ＞ ポイント履歴
 *
 * 利用DBテーブル:
 * - point_transactions: ポイント増減履歴・変動後残高・理由・発生日時の取得（参照）
 * - students: 生徒コード・氏名・学年・在籍状態の取得（参照）
 * - grades: 学年名称と検索候補の取得（参照）
 * - enrollment_statuses: 在籍状態名称と検索候補の取得（参照）
 *
 * この画面ではポイント履歴を更新しない。
 */
class PointHistoryController extends Controller
{
    /**
     * ポイント履歴一覧を表示する。
     */
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $filters = $this->applyDefaultPeriod($filters, $request);

        $query = $this->buildHistoryQuery();
        $this->applyFilters($query, $filters);

        // 検索条件に連動したサマリーを、ページネーション前の対象履歴全件から算出する。
        $summarySource = DB::query()->fromSub(clone $query, 'point_history_summary');
        $earned = (int) (clone $summarySource)->where('points', '>', 0)->sum('points');
        $used = abs((int) (clone $summarySource)->where('points', '<', 0)->sum('points'));
        $summary = [
            'earned' => $earned,
            'used' => $used,
            'net' => $earned - $used,
            'count' => (int) (clone $summarySource)->count(),
        ];

        [$sort, $direction] = $this->resolveSort($filters);
        $this->applySort($query, $sort, $direction);

        $perPage = (int) ($filters['per_page'] ?? 50);
        $histories = $query->paginate($perPage)->withQueryString();

        return view('admin.wakuwaku.points.history', [
            'histories' => $histories,
            'summary' => $summary,
            'grades' => DB::table('grades')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'enrollmentStatuses' => DB::table('enrollment_statuses')->where('is_active', true)->orderBy('sort_order')->get(['id', 'status']),
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * 履歴表示に必要なQueryを組み立てる。
     */
    private function buildHistoryQuery(): Builder
    {
        return DB::table('point_transactions as pt')
            ->join('students as s', 's.id', '=', 'pt.student_id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('enrollment_statuses as es', 'es.id', '=', 's.enrollment_status_id')
            ->select([
                'pt.id',
                'pt.student_id',
                'pt.event_type',
                'pt.event_type_name',
                'pt.point_rule_code',
                'pt.points',
                'pt.balance_after',
                'pt.reason',
                'pt.related_id',
                'pt.occurred_at',
                'pt.created_at',
                's.student_code',
                's.last_name',
                's.first_name',
                's.last_name_kana',
                's.first_name_kana',
                's.grade_id',
                's.enrollment_status_id',
                'g.name as grade_name',
                'g.sort_order as grade_sort_order',
                'es.status as enrollment_status_name',
            ]);
    }

    /**
     * GETパラメータを画面で許可する値だけに絞る。
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'student_id' => ['nullable', 'integer'],
            'grade_id' => ['nullable', 'integer'],
            'enrollment_status_id' => ['nullable', 'integer'],
            'point_type' => ['nullable', 'in:earned,used'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort' => ['nullable', 'in:occurred_at,student_code,name,grade,points,balance_after,event_type'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
        ]);
    }

    /**
     * 初回表示時だけ当月を対象期間として設定する。
     */
    private function applyDefaultPeriod(array $filters, Request $request): array
    {
        if (! $request->hasAny(['start_date', 'end_date'])) {
            $filters['start_date'] = now()->startOfMonth()->toDateString();
            $filters['end_date'] = now()->endOfMonth()->toDateString();
        }

        return $filters;
    }

    /**
     * 検索条件を履歴Queryへ適用する。
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
                    ->orWhere('s.first_name_kana', 'ILIKE', "%{$keyword}%")
                    ->orWhere('pt.reason', 'ILIKE', "%{$keyword}%")
                    ->orWhere('pt.event_type_name', 'ILIKE', "%{$keyword}%");
            });
        }

        if (! empty($filters['student_id'])) {
            $query->where('pt.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['grade_id'])) {
            $query->where('s.grade_id', (int) $filters['grade_id']);
        }

        if (! empty($filters['enrollment_status_id'])) {
            $query->where('s.enrollment_status_id', (int) $filters['enrollment_status_id']);
        }

        if (($filters['point_type'] ?? null) === 'earned') {
            $query->where('pt.points', '>', 0);
        } elseif (($filters['point_type'] ?? null) === 'used') {
            $query->where('pt.points', '<', 0);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('pt.occurred_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('pt.occurred_at', '<=', $filters['end_date']);
        }
    }

    /**
     * ソート指定を安全な初期値へ正規化する。
     */
    private function resolveSort(array $filters): array
    {
        return [
            $filters['sort'] ?? 'occurred_at',
            $filters['direction'] ?? 'desc',
        ];
    }

    /**
     * 許可済みのソート条件を履歴Queryへ適用する。
     */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'student_code' => $query->orderBy('s.student_code', $direction),
            'name' => $query->orderBy('s.last_name_kana', $direction)->orderBy('s.first_name_kana', $direction),
            'grade' => $query->orderByRaw('g.sort_order IS NULL')->orderBy('g.sort_order', $direction),
            'points' => $query->orderBy('pt.points', $direction),
            'balance_after' => $query->orderBy('pt.balance_after', $direction),
            'event_type' => $query->orderBy('pt.event_type_name', $direction),
            default => $query->orderBy('pt.occurred_at', $direction),
        };

        $query->orderBy('pt.id', $direction);
    }
}
