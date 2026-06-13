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

            'school',
            'grade',
            'enrollmentStatus',

            'pointBalance',
            'pointTransactions',
            'attendances',

            'studentClasses.classroom',
            'studentTeachers.teacher',

            'courseContracts.course',
            'courseContracts.coursePrice',

            'lessonReservations.lessonSession',

            'studentBadges.badge',
            'studentTitles.title',
            'titles',

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

        if ($packageId = trim((string) request('routine_package_id', ''))) {
            $packageQuery->whereRaw("CAST(id AS TEXT) LIKE ?", ['%' . preg_replace('/[^0-9]/', '', $packageId) . '%']);
        }

        if ($keyword = trim((string) request('routine_package_keyword', ''))) {
            $packageQuery->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%")
                    ->orWhere('tag', 'like', "%{$keyword}%");
            });
        }

        if (($grade = request('routine_package_grade')) && $grade !== 'all') {
            if ($grade === 'ALL') {
                $packageQuery->where('target_grade', 'ALL');
            } else {
                $packageQuery->where('target_grade', 'like', '%' . $grade . '%');
            }
        }

        if (($level = request('routine_package_level')) && $level !== 'all') {
            $packageQuery->where('target_level', $level);
        }

        if (($category = request('routine_package_category')) && $category !== 'all') {
            $packageQuery->where('category', $category);
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

        $addedRoutinePackageItemIds = StudentRoutineItem::query()
            ->whereHas('routine', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->whereNotNull('routine_package_item_id')
            ->pluck('routine_package_item_id')
            ->unique()
            ->values();

        if ($contentId = trim((string) request('routine_item_content_id', ''))) {
            $id = preg_replace('/[^0-9]/', '', $contentId);

            if ($id !== '') {
                $itemQuery->whereRaw("CAST(routine_content_id AS TEXT) LIKE ?", ['%' . $id . '%']);
            }
        }

        if ($keyword = trim((string) request('routine_item_keyword', ''))) {
            $itemQuery->where(function ($query) use ($keyword) {
                $query->where('item_name', 'like', "%{$keyword}%")
                    ->orWhere('memo', 'like', "%{$keyword}%")
                    ->orWhere('tag', 'like', "%{$keyword}%");
            });
        }

        if (($grade = request('routine_item_grade')) && $grade !== 'all') {
            if ($grade === '未設定') {
                $itemQuery->where(function ($query) {
                    $query->whereNull('target_grade')->orWhere('target_grade', '');
                });
            } elseif ($grade === 'ALL') {
                $itemQuery->where('target_grade', 'ALL');
            } else {
                $itemQuery->where('target_grade', 'like', '%' . $grade . '%');
            }
        }

        if (($level = request('routine_item_level')) && $level !== 'all') {
            if ($level === '未設定') {
                $itemQuery->where(function ($query) {
                    $query->whereNull('target_level')->orWhere('target_level', '');
                });
            } else {
                $itemQuery->where('target_level', $level);
            }
        }

        if (($completionType = request('routine_item_completion_type')) && $completionType !== 'all') {
            if ($completionType === '未設定') {
                $itemQuery->whereNull('completion_type_id');
            } else {
                $itemQuery->where('completion_type_id', (int) $completionType);
            }
        }

        if (($addStatus = request('routine_item_add_status')) && $addStatus !== 'all') {
            if ($addStatus === 'not_added') {
                $itemQuery->whereNotIn('id', $addedRoutinePackageItemIds);
            } elseif ($addStatus === 'added') {
                $itemQuery->whereIn('id', $addedRoutinePackageItemIds);
            }
        }

        $routinePackageItems = $itemQuery
            ->orderBy('routine_package_id')
            ->orderBy('order_no')
            ->limit(5)
            ->get();

        $gradeOrder = ['ALL', 'PRE', 'K1', 'K2', 'K3', 'E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'J1', 'J2', 'J3', 'H1', 'H2', 'H3', '未設定'];
        $itemGradeValues = RoutinePackageItem::query()
            ->pluck('target_grade')
            ->flatMap(function ($grade) {
                if ($grade === null || $grade === '') {
                    return ['未設定'];
                }

                if ($grade === 'ALL') {
                    return ['ALL'];
                }

                return collect(explode(',', $grade))->map(fn($value) => trim($value))->filter();
            })
            ->unique()
            ->values();

        $itemGrades = collect($gradeOrder)
            ->filter(fn($grade) => $itemGradeValues->contains($grade))
            ->values();

        $levelOrder = ['Lv1', 'Lv2', 'Lv3', 'Lv4', 'Lv5', 'Lv6', 'Lv7', 'Lv8', 'Lv9', 'Lv10', '初級', '中級', '上級', '標準', '未設定'];
        $itemLevelValues = RoutinePackageItem::query()
            ->pluck('target_level')
            ->map(fn($level) => ($level === null || $level === '') ? '未設定' : $level)
            ->unique()
            ->values();

        $itemLevels = collect($levelOrder)
            ->filter(fn($level) => $itemLevelValues->contains($level))
            ->values();

        $itemCompletionTypes = \App\Models\RoutineCompletionType::query()
            ->where('is_active', true)
            ->whereIn('id', RoutinePackageItem::query()
                ->whereNotNull('completion_type_id')
                ->distinct()
                ->pluck('completion_type_id')
                ->filter()
                ->values()
            )
            ->orderBy('sort_order')
            ->get();

        $hasUnsetItemCompletionType = RoutinePackageItem::query()
            ->whereNull('completion_type_id')
            ->exists();

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
            'itemCompletionTypes' => $itemCompletionTypes,
            'hasUnsetItemCompletionType' => $hasUnsetItemCompletionType,
            'today' => Carbon::parse($today),
            'schools' => \App\Models\School::where('is_active', true)->orderBy('sort_order')->get(),
            'grades' => \App\Models\Grade::orderBy('id')->get(),
            'teachers' => \App\Models\Teacher::where('is_active', true)->orderBy('id')->get(),
            'enrollmentStatuses' => \App\Models\EnrollmentStatus::orderBy('id')->get(),

            'coursePrices' => \App\Models\CoursePrice::with('course')
                ->where('is_active', true)
                ->orderBy('course_id')
                ->orderBy('sort_order')
                ->get(),
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

    

    public function applyRoutineItemPackage(Request $request, Student $student)
    {
        $validated = $request->validate([
            'routine_package_item_id' => ['required','integer','exists:routine_package_items,id'],
            'student_routine_id' => ['required','integer','exists:student_routines,id'],
        ]);

        $targetRoutine = \App\Models\StudentRoutine::query()
            ->where('id', $validated['student_routine_id'])
            ->where('student_id', $student->id)
            ->firstOrFail();

        $source = \App\Models\RoutinePackageItem::findOrFail($validated['routine_package_item_id']);

        $nextOrderNo = ((int) \App\Models\StudentRoutineItem::query()
            ->where('student_routine_id', $targetRoutine->id)
            ->max('order_no')) + 1;

        $createdItem = \App\Models\StudentRoutineItem::create([
            'student_routine_id' => $targetRoutine->id,
            'routine_package_item_id' => $source->id,
            'routine_content_id' => $source->routine_content_id,
            'item_name' => $source->item_name,
            'target_grade' => $source->target_grade,
            'target_level' => $source->target_level,
            'tag' => $source->tag,
            'completion_type_id' => $source->completion_type_id,
            'target_value' => $source->target_value,
            'estimated_minutes' => $source->estimated_minutes,
            'order_no' => $nextOrderNo,
            'is_required' => $source->is_required,
            'is_active' => true,
            'memo' => $source->memo,
            'required_days' => $source->required_days,
            'start_date' => now()->toDateString(),
        ]);

        $requiredDays = max(1, (int)($source->required_days ?? 1));

        for ($day = 0; $day < $requiredDays; $day++) {
            \Illuminate\Support\Facades\DB::table('student_routine_daily_statuses')->insert([
                'student_id' => $student->id,
                'student_routine_item_id' => $createdItem->id,
                'target_date' => now()->addDays($day)->toDateString(),
                'status' => 'pending',
                'achieved_days' => 0,
                'elapsed_days' => $day + 1,
                'achievement_rate' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('admin.students.karte.show', [
            'student' => $student->id,
            'tab' => 'routine',
        ])->with('success', 'ルーティンアイテムを追加しました。');
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
            'last_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name_kana' => ['nullable', 'string', 'max:255'],
            'first_name_kana' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'enrolled_at' => ['nullable', 'date'],
            'profile_image' => ['nullable', 'image', 'max:2048'],
            'grade_id' => ['nullable', 'integer'],
            'school_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'enrollment_status_id' => ['nullable', 'integer', 'exists:enrollment_statuses,id'],
            'course_price_id' => ['nullable', 'integer', 'exists:course_prices,id'],

        ]);

        unset($validated['profile_image']);

        if ($request->hasFile('profile_image')) {
            $extension = $request->file('profile_image')->getClientOriginalExtension();

            $path = $request->file('profile_image')->storeAs(
                'students/' . $student->id,
                'profile-image.' . $extension,
                's3'
            );

            $validated['image_path'] = $path;
        }

        $teacherId = $validated['teacher_id'] ?? null;

        unset($validated['teacher_id']);

        if ($teacherId) {
            \App\Models\StudentTeacher::where('student_id', $student->id)
                ->where('is_primary', true)
                ->update(['is_active' => false]);

            \App\Models\StudentTeacher::create([
                'student_id' => $student->id,
                'teacher_id' => $teacherId,
                'is_primary' => true,
                'is_active' => true,
            ]);
        }

        $teacherId = $validated['teacher_id'] ?? null;
        unset($validated['teacher_id']);

        if ($teacherId) {
            \App\Models\StudentTeacher::where('student_id', $student->id)
                ->where('is_primary', true)
                ->update(['is_active' => false]);

            \App\Models\StudentTeacher::create([
                'student_id' => $student->id,
                'teacher_id' => $teacherId,
                'is_primary' => true,
                'is_active' => true,
            ]);
        }

        $coursePriceId = $validated['course_price_id'] ?? null;
        
        unset($validated['course_price_id']);

        if ($coursePriceId) {
            $coursePrice = \App\Models\CoursePrice::findOrFail($coursePriceId);

            \App\Models\StudentCourseContract::where('student_id', $student->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'ended_at' => now()->toDateString(),
                ]);

            \App\Models\StudentCourseContract::create([
                'student_id' => $student->id,
                'course_id' => $coursePrice->course_id,
                'course_price_id' => $coursePrice->id,
                'contract_status' => 'active',
                'started_at' => now()->toDateString(),
                'ended_at' => null,
                'monthly_fee' => $coursePrice->monthly_fee,
                'note' => null,
                'is_active' => true,
            ]);
        }


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