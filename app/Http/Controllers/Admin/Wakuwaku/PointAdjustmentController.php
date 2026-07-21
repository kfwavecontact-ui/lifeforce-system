<?php

namespace App\Http\Controllers\Admin\Wakuwaku;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ポイント調整画面を管理するController。
 *
 * 関連画面:
 * - わくわく ＞ ポイント ＞ ポイント調整
 *
 * 利用DBテーブル:
 * - students: 調整対象生徒の検索・表示（参照）
 * - grades: 学年名称・検索候補（参照）
 * - enrollment_statuses: 在籍状態名称・検索候補（参照）
 * - student_point_balances: 現在残高・累計獲得・累計利用（参照／更新）
 * - point_transactions: 手動調整履歴（追加）
 *
 * 残高更新と履歴追加は必ず同一DBトランザクション内で実行する。
 */
class PointAdjustmentController extends Controller
{
    /**
     * 生徒検索一覧と、選択中の生徒の調整フォームを表示する。
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'grade_id' => ['nullable', 'integer'],
            'enrollment_status_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $studentsQuery = $this->buildStudentQuery();
        $this->applyStudentFilters($studentsQuery, $filters);

        $students = $studentsQuery
            ->orderBy('s.student_code')
            ->paginate(20)
            ->withQueryString();

        $selectedStudent = null;
        if (! empty($filters['student_id'])) {
            $selectedStudent = $this->buildStudentQuery()
                ->where('s.id', (int) $filters['student_id'])
                ->first();
        }

        return view('admin.wakuwaku.points.adjustment', [
            'students' => $students,
            'selectedStudent' => $selectedStudent,
            'grades' => DB::table('grades')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'enrollmentStatuses' => DB::table('enrollment_statuses')->where('is_active', true)->orderBy('sort_order')->get(['id', 'status']),
            'filters' => $filters,
        ]);
    }

    /**
     * 手動ポイント調整を保存する。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'adjustment_type' => ['required', 'in:add,subtract'],
            'points' => ['required', 'integer', 'min:1', 'max:999999'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'student_id.required' => '調整する生徒を選択してください。',
            'adjustment_type.required' => '加算または減算を選択してください。',
            'points.required' => '調整ポイントを入力してください。',
            'points.integer' => '調整ポイントは整数で入力してください。',
            'points.min' => '調整ポイントは1以上で入力してください。',
            'reason.required' => '調整理由を入力してください。',
            'reason.min' => '調整理由は3文字以上で入力してください。',
        ]);

        $student = DB::table('students')
            ->where('id', (int) $validated['student_id'])
            ->first(['id', 'student_code', 'last_name', 'first_name']);

        DB::transaction(function () use ($validated): void {
            $studentId = (int) $validated['student_id'];
            $points = (int) $validated['points'];
            $isAdd = $validated['adjustment_type'] === 'add';

            $balance = DB::table('student_point_balances')
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->first();

            // 残高レコードが未作成の生徒は、0ポイントの初期レコードを作成する。
            if (! $balance) {
                DB::table('student_point_balances')->insert([
                    'student_id' => $studentId,
                    'current_points' => 0,
                    'total_earned_points' => 0,
                    'total_used_points' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $balance = DB::table('student_point_balances')
                    ->where('student_id', $studentId)
                    ->lockForUpdate()
                    ->first();
            }

            $signedPoints = $isAdd ? $points : -$points;
            $newBalance = (int) $balance->current_points + $signedPoints;

            // 手動減算によって残高がマイナスになる操作は許可しない。
            if (! $isAdd && $newBalance < 0) {
                throw ValidationException::withMessages([
                    'points' => '減算後の残高がマイナスになります。現在ポイント以下の値を入力してください。',
                ]);
            }

            DB::table('student_point_balances')
                ->where('id', $balance->id)
                ->update([
                    'current_points' => $newBalance,
                    'total_earned_points' => (int) $balance->total_earned_points + ($isAdd ? $points : 0),
                    'total_used_points' => (int) $balance->total_used_points + ($isAdd ? 0 : $points),
                    'updated_at' => now(),
                ]);

            DB::table('point_transactions')->insert([
                'student_id' => $studentId,
                'event_type' => 'manual_adjustment',
                'event_type_name' => '手動ポイント調整',
                'point_rule_code' => $isAdd ? 'manual_add' : 'manual_subtract',
                'points' => $signedPoints,
                'balance_after' => $newBalance,
                'reason' => trim($validated['reason']),
                'related_id' => null,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        $typeLabel = $validated['adjustment_type'] === 'add' ? '加算' : '減算';

        return redirect()
            ->route('admin.wakuwaku.points.adjustment', ['student_id' => $validated['student_id']])
            ->with('success', sprintf(
                '%s %sさんへ%sptを%sしました。',
                $student->student_code,
                $student->last_name . ' ' . $student->first_name,
                number_format((int) $validated['points']),
                $typeLabel
            ));
    }

    /**
     * 生徒一覧と選択生徒表示に共通利用するQueryを組み立てる。
     */
    private function buildStudentQuery(): Builder
    {
        return DB::table('students as s')
            ->leftJoin('student_point_balances as spb', 'spb.student_id', '=', 's.id')
            ->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->leftJoin('enrollment_statuses as es', 'es.id', '=', 's.enrollment_status_id')
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
                'es.status as enrollment_status_name',
            ])
            ->selectRaw('COALESCE(spb.current_points, 0) AS current_points')
            ->selectRaw('COALESCE(spb.total_earned_points, 0) AS total_earned_points')
            ->selectRaw('COALESCE(spb.total_used_points, 0) AS total_used_points');
    }

    /**
     * 生徒検索条件を適用する。
     */
    private function applyStudentFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where(function (Builder $subQuery) use ($keyword): void {
                $subQuery->where('s.student_code', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.last_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.first_name', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.last_name_kana', 'ILIKE', "%{$keyword}%")
                    ->orWhere('s.first_name_kana', 'ILIKE', "%{$keyword}%");
            });
        }

        if (! empty($filters['grade_id'])) {
            $query->where('s.grade_id', (int) $filters['grade_id']);
        }

        if (! empty($filters['enrollment_status_id'])) {
            $query->where('s.enrollment_status_id', (int) $filters['enrollment_status_id']);
        }
    }
}
