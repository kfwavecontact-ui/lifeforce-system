<?php

namespace App\Services\Routine;

use App\Models\StudentRoutine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 生徒カルテと教育ルーティン画面で共通利用する読取Service。
 * 関連画面: 生徒カルテ＞ルーティン、教育＞ルーティン一覧
 * 利用DB: student_routines, student_routine_items, routine_contents, routine_completion_types
 * 参照/更新区分: 参照のみ
 */
class StudentRoutineReadService
{
    public function activeQuery(int $studentId, CarbonInterface|string|null $targetDate = null): Builder
    {
        $date = $targetDate ? (string) $targetDate : now()->toDateString();

        return StudentRoutine::query()
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $date);
            })
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->with([
                'items' => fn ($query) => $query->where('is_active', true)->orderBy('order_no'),
                'items.routineContent',
                'items.completionType',
            ])
            ->orderBy('start_date')
            ->orderBy('id');
    }

    public function activeForStudent(int $studentId, CarbonInterface|string|null $targetDate = null): Collection
    {
        return $this->activeQuery($studentId, $targetDate)->get();
    }
}
