<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoutinePackage;
use App\Models\RoutinePackageItem;
use App\Models\Student;
use App\Models\StudentRoutine;
use App\Models\StudentRoutineDailyStatus;
use App\Models\StudentRoutineItem;
use App\Services\Routine\PackageApplyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentKarteController extends Controller
{
    public function show(Student $student)
    {
        $student->load([
            'user',
            'parents',
            'lessonReservations.lessonSession',
            'studentBadges.badge',
            'studentTitles.title',
            'lessonNotes.user',
        ]);

        $today = now()->toDateString();

        $activeRoutines = StudentRoutine::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->with([
                'items.routineContent',
                'items.completionType',
            ])
            ->orderBy('start_date')
            ->get();

        $endedRoutines = StudentRoutine::query()
            ->where('student_id', $student->id)
            ->with([
                'items.routineContent',
                'items.completionType',
            ])
            ->get();

        $endedRoutineItemsAll = StudentRoutineItem::query()
            ->with([
                'routine',
                'routineContent',
                'completionType',
            ])
            ->whereHas('routine', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->where('is_active', false)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($item) {
                $item->setRelation('parentRoutine', $item->routine);
                return $item;
            })
            ->values();

        $endedRoutineItems = $endedRoutineItemsAll->take(20);
        $endedRoutineItemsTotal = $endedRoutineItemsAll->count();

        $activeItemIds = $activeRoutines
            ->flatMap(fn ($routine) => $routine->items)
            ->pluck('id')
            ->values();

        $endedItemIds = $endedRoutineItemsAll
            ->pluck('id')
            ->values();

        $allItemIds = $activeItemIds
            ->merge($endedItemIds)
            ->unique()
            ->values();

        $todayStatuses = StudentRoutineDailyStatus::query()
            ->whereIn('student_routine_item_id', $activeItemIds)
            ->whereDate('target_date', $today)
            ->get()
            ->keyBy('student_routine_item_id');

        $allStatuses = StudentRoutineDailyStatus::query()
            ->whereIn('student_routine_item_id', $allItemIds)
            ->orderBy('target_date')
            ->get()
            ->groupBy('student_routine_item_id');

        $calendarStatuses = StudentRoutineDailyStatus::query()
            ->whereIn('student_routine_item_id', $allItemIds)
            ->orderBy('target_date')
            ->get()
            ->groupBy('student_routine_item_id');

        $packageQuery = RoutinePackage::query()
            ->where('is_active', true);

        if ($packageId = request('routine_package_id')) {
            $id = preg_replace('/[^0-9]/', '', $packageId);

            if ($id !== '') {
                $packageQuery->where('id', (int) $id);
            }
        }

        if ($keyword = request('routine_package_keyword')) {
            $packageQuery->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if (($grade = request('routine_package_grade')) && $grade !== 'all') {
            $packageQuery->where('target_grade', $grade);
        }

        if (($level = request('routine_package_level')) && $level !== 'all') {
            $packageQuery->where('target_level', $level);
        }

        if (($category = request('routine_package_category')) && $category !== 'all') {
            $packageQuery->where('category', $category);
        }

        if (($tag = request('routine_package_tag')) && $tag !== 'all') {
            $packageQuery->where('tag', $tag);
        }

        $routinePackages = $packageQuery
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $packageGrades = RoutinePackage::query()
            ->whereNotNull('target_grade')
            ->distinct()
            ->orderBy('target_grade')
            ->pluck('target_grade');

        $packageLevels = RoutinePackage::query()
            ->whereNotNull('target_level')
            ->distinct()
            ->orderBy('target_level')
            ->pluck('target_level');

        $packageCategories = RoutinePackage::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $packageTags = RoutinePackage::query()
            ->whereNotNull('tag')
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag');

        $itemQuery = RoutinePackageItem::query()
            ->with([
                'routineContent',
                'completionType',
                'package',
            ]);

        if ($contentId = request('routine_item_content_id')) {
            $id = preg_replace('/[^0-9]/', '', $contentId);

            if ($id !== '') {
                $itemQuery->where('routine_content_id', (int) $id);
            }
        }

        if ($keyword = request('routine_item_keyword')) {
            $itemQuery->where(function ($query) use ($keyword) {
                $query->where('item_name', 'like', "%{$keyword}%")
                    ->orWhere('memo', 'like', "%{$keyword}%");
            });
        }

        if (($grade = request('routine_item_grade')) && $grade !== 'all') {
            $itemQuery->where('target_grade', $grade);
        }

        if (($level = request('routine_item_level')) && $level !== 'all') {
            $itemQuery->where('target_level', $level);
        }

        if (($tag = request('routine_item_tag')) && $tag !== 'all') {
            $itemQuery->where('tag', $tag);
        }

        $routinePackageItems = $itemQuery
            ->orderBy('routine_package_id')
            ->orderBy('order_no')
            ->limit(5)
            ->get();

        $itemGrades = RoutinePackageItem::query()
            ->whereNotNull('target_grade')
            ->distinct()
            ->orderBy('target_grade')
            ->pluck('target_grade');

        $itemLevels = RoutinePackageItem::query()
            ->whereNotNull('target_level')
            ->distinct()
            ->orderBy('target_level')
            ->pluck('target_level');

        $itemTags = RoutinePackageItem::query()
            ->whereNotNull('tag')
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag');

        return view('admin.students.karte.index', [
            'student' => $student,
            'activeRoutine' => $activeRoutines->first(),
            'activeRoutines' => $activeRoutines,
            'endedRoutines' => $endedRoutines,
            'endedRoutineItems' => $endedRoutineItems,
            'endedRoutineItemsTotal' => $endedRoutineItemsTotal,
            'todayStatuses' => $todayStatuses,
            'allStatuses' => $allStatuses,
            'calendarStatuses' => $calendarStatuses,
            'period' => request('period', 'this_week'),
            'routinePeriods' => collect(),
            'routineLogs' => collect(),
            'routinePackages' => $routinePackages,
            'routinePackageItems' => $routinePackageItems,
            'packageGrades' => $packageGrades,
            'packageLevels' => $packageLevels,
            'packageCategories' => $packageCategories,
            'packageTags' => $packageTags,
            'itemGrades' => $itemGrades,
            'itemLevels' => $itemLevels,
            'itemTags' => $itemTags,
            'today' => Carbon::parse($today),
        ]);
    }

    public function routineHistory(Student $student)
    {
        $student->load(['user']);

        $endedRoutineItems = StudentRoutineItem::query()
            ->with([
                'routine',
                'routineContent',
                'completionType',
            ])
            ->whereHas('routine', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->where('is_active', false)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('updated_at')
            ->get();

        $itemIds = $endedRoutineItems->pluck('id')->values();

        $allStatuses = StudentRoutineDailyStatus::query()
            ->whereIn('student_routine_item_id', $itemIds)
            ->orderBy('target_date')
            ->get()
            ->groupBy('student_routine_item_id');

        $calendarStatuses = StudentRoutineDailyStatus::query()
            ->whereIn('student_routine_item_id', $itemIds)
            ->orderBy('target_date')
            ->get()
            ->groupBy('student_routine_item_id');

        return view('admin.students.karte.routines.history', [
            'student' => $student,
            'endedRoutineItems' => $endedRoutineItems,
            'allStatuses' => $allStatuses,
            'calendarStatuses' => $calendarStatuses,
        ]);
    }

    public function applyRoutinePackage(
        Request $request,
        Student $student,
        PackageApplyService $packageApplyService
    ) {
        $validated = $request->validate([
            'routine_package_id' => ['required', 'integer', 'exists:routine_packages,id'],
        ]);

        $packageApplyService->apply(
            $student->id,
            (int) $validated['routine_package_id'],
            Auth::id()
        );

        return redirect()
            ->route('admin.students.karte.show', [
                'student' => $student->id,
                'tab' => 'routine',
            ])
            ->with('success', 'ルーティンパッケージを追加しました。');
    }

    public function updateRoutineItem(Request $request, Student $student, StudentRoutineItem $item)
    {
        if ((int) $item->routine->student_id !== (int) $student->id) {
            abort(403);
        }

        if ($request->boolean('teacher_comment_mode')) {
            $validated = $request->validate([
                'teacher_comment' => ['nullable', 'string'],
            ]);

            $item->update([
                'teacher_comment' => $validated['teacher_comment'] ?? null,
                'teacher_comment_user_id' => Auth::id(),
                'teacher_comment_at' => now(),
            ]);

            return redirect()
                ->route('admin.students.karte.show', [
                    'student' => $student->id,
                    'tab' => 'routine',
                ])
                ->with('success', '担当コメントを更新しました。');
        }

        if ($request->boolean('move_mode')) {
            $validated = $request->validate([
                'move_to_student_routine_id' => ['required', 'integer', 'exists:student_routines,id'],
            ]);

            $targetRoutine = StudentRoutine::query()
                ->where('id', $validated['move_to_student_routine_id'])
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->firstOrFail();

            $nextOrderNo = ((int) StudentRoutineItem::query()
                ->where('student_routine_id', $targetRoutine->id)
                ->max('order_no')) + 1;

            $item->update([
                'student_routine_id' => $targetRoutine->id,
                'order_no' => $nextOrderNo,
            ]);

            return redirect()
                ->route('admin.students.karte.show', [
                    'student' => $student->id,
                    'tab' => 'routine',
                ])
                ->with('success', 'ルーティンアイテムを移動しました。');
        }

        $validated = $request->validate([
            'completion_type_id' => ['required', 'integer', 'exists:routine_completion_types,id'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'memo' => ['nullable', 'string'],
        ]);

        $item->update([
            'completion_type_id' => $validated['completion_type_id'],
            'target_value' => $validated['target_value'],
            'estimated_minutes' => $validated['estimated_minutes'],
            'memo' => $validated['memo'],
        ]);

        return redirect()
            ->route('admin.students.karte.show', [
                'student' => $student->id,
                'tab' => 'routine',
            ])
            ->with('success', 'ルーティンアイテムを更新しました。');
    }


    public function deleteRoutineItem(Student $student, StudentRoutineItem $item)
    {
        if ((int) $item->routine->student_id !== (int) $student->id) {
            abort(403);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($item) {
            StudentRoutineDailyStatus::query()
                ->where('student_routine_item_id', $item->id)
                ->delete();

            $item->delete();
        });

        return redirect()
            ->route('admin.students.karte.show', [
                'student' => $student->id,
                'tab' => 'routine',
            ])
            ->with('success', 'ルーティンアイテムを削除しました。');
    }

    public function updateRoutineDailyStatus(
        Request $request,
        Student $student,
        StudentRoutineItem $item
    ) {
        if ((int) $item->routine->student_id !== (int) $student->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:completed,partial,not_started'],
            'study_minutes' => ['nullable', 'integer', 'min:0'],
            'comment' => ['nullable', 'string'],
        ]);

        $studySeconds = !empty($validated['study_minutes'])
            ? (int) $validated['study_minutes'] * 60
            : null;

        StudentRoutineDailyStatus::updateOrCreate(
            [
                'student_id' => $student->id,
                'student_routine_item_id' => $item->id,
                'target_date' => now()->toDateString(),
            ],
            [
                'status' => $validated['status'],
                'achieved_days' => $validated['status'] === 'completed' ? 1 : 0,
                'elapsed_days' => 1,
                'achievement_rate' => $validated['status'] === 'completed' ? 100 : 0,
                'studied_at' => $validated['status'] === 'not_started' ? null : now(),
                'study_seconds' => $studySeconds,
                'comment' => $validated['comment'] ?? null,
                'updated_at' => now(),
            ]
        );

        return redirect()
            ->route('admin.students.karte.show', [
                'student' => $student->id,
                'tab' => 'routine',
            ])
            ->with('success', '本日の実績を保存しました。');
    }


    public function edit(Student $student)
    {
        $student->load([
            'user',
            'parents',
            'lessonReservations.lessonSession',
            'studentBadges.badge',
            'studentTitles.title',
            'lessonNotes.user',
        ]);

        return view('admin.students.karte.edit', [
            'student' => $student,
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'gender' => ['nullable', 'string', 'max:20'],
            'birthday' => ['nullable', 'date'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'commute_days' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $student->update($validated);

        return redirect()
            ->route('admin.students.karte.show', $student)
            ->with('success', '生徒情報を更新しました。');
    }

    public function completeRoutine(Request $request, Student $student)
    {
        $validated = $request->validate([
            'routine_name' => ['required', 'string', 'max:255'],
        ]);

        StudentRoutine::create([
            'student_id' => $student->id,
            'routine_package_id' => null,
            'name' => $validated['routine_name'],
            'description' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.students.karte.show', [
                'student' => $student->id,
                'tab' => 'routine',
            ])
            ->with('success', '空のルーティンを作成しました。');
    }

    public function cancelRoutine(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_routine_id' => ['required', 'integer', 'exists:student_routines,id'],
        ]);

        $routine = StudentRoutine::query()
            ->with(['items'])
            ->where('id', $validated['student_routine_id'])
            ->where('student_id', $student->id)
            ->firstOrFail();

        $activeItemCount = $routine->items
            ->where('is_active', true)
            ->count();

        if ($activeItemCount > 0) {
            return redirect()
                ->route('admin.students.karte.show', [
                    'student' => $student->id,
                    'tab' => 'routine',
                ])
                ->with('success', 'アイテムが残っているため、パックは削除できません。');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($routine) {
            $itemIds = $routine->items->pluck('id');

            if ($itemIds->isNotEmpty()) {
                StudentRoutineDailyStatus::query()
                    ->whereIn('student_routine_item_id', $itemIds)
                    ->delete();

                StudentRoutineItem::query()
                    ->whereIn('id', $itemIds)
                    ->delete();
            }

            $routine->delete();
        });

        return redirect()
            ->route('admin.students.karte.show', [
                'student' => $student->id,
                'tab' => 'routine',
            ])
            ->with('success', '空のパックを削除しました。');
    }

    public function finishRoutine(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_routine_item_id' => ['required', 'integer', 'exists:student_routine_items,id'],
            'self_evaluation_score' => ['required', 'integer', 'min:1', 'max:5'],
            'self_evaluation_comment' => ['nullable', 'string'],
            'teacher_comment' => ['nullable', 'string'],
        ]);

        $item = StudentRoutineItem::query()
            ->where('id', $validated['student_routine_item_id'])
            ->firstOrFail();

        if ((int) $item->routine->student_id !== (int) $student->id) {
            abort(403);
        }

        $item->update([
            'is_active' => false,
            'completed_at' => now(),
            'self_evaluation_score' => $validated['self_evaluation_score'],
            'self_evaluation_comment' => $validated['self_evaluation_comment'] ?? null,
            'teacher_comment' => $validated['teacher_comment'] ?? null,
            'teacher_comment_user_id' => Auth::id(),
            'teacher_comment_at' => now(),
        ]);

        return redirect()
            ->route('admin.students.karte.show', [
                'student' => $student->id,
                'tab' => 'routine',
            ])
            ->with('success', 'ルーティンアイテムを完了しました。');
    }
}