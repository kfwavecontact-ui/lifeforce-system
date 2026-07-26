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
use App\Services\Routine\StudentRoutineReadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentKarteController extends Controller
{
    public function show(Student $student, StudentRoutineReadService $studentRoutineReadService)
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

        // 生徒カルテと教育＞ルーティン一覧で同じ実施中条件を共通利用する。
        $activeRoutines = $studentRoutineReadService->activeForStudent($student->id, $today);

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
            ->with(['items.routineContent'])
            ->where('is_active', true);

        if ($packageId = trim((string) request('routine_package_id', ''))) {
            $numericId = preg_replace('/[^0-9]/', '', $packageId);
            $packageQuery->where(function ($query) use ($packageId, $numericId) {
                $query->where('package_code', 'like', "%{$packageId}%");
                if ($numericId !== '') {
                    $query->orWhereRaw("CAST(id AS TEXT) LIKE ?", ['%' . $numericId . '%']);
                }
            });
        }

        if ($keyword = trim((string) request('routine_package_keyword', ''))) {
            $packageQuery->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%")
                    ->orWhere('search_tags', 'like', "%{$keyword}%")
                    ->orWhere('tag', 'like', "%{$keyword}%");
            });
        }

        if (($grade = request('routine_package_grade')) && $grade !== 'all') {
            if ($grade === '未設定') {
                $packageQuery->where(function ($query) {
                    $query->whereNull('target_grade')->orWhere('target_grade', '');
                });
            } elseif ($grade === 'ALL') {
                $packageQuery->where('target_grade', 'ALL');
            } else {
                $packageQuery->where('target_grade', 'like', '%' . $grade . '%');
            }
        }

        if (($level = request('routine_package_level')) && $level !== 'all') {
            if ($level === '未設定') {
                $packageQuery->where(function ($query) {
                    $query->whereNull('target_level')->orWhere('target_level', '');
                });
            } else {
                $packageQuery->where('target_level', $level);
            }
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


        $packageTags = RoutinePackage::query()
            ->whereNotNull('tag')
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag');

        // 単体追加候補は、同じ共通アイテムがルーティンごとに重複しないよう、
        // routine_content_idごとに代表となるroutine_package_itemsを1件だけ取得する。
        // 表示・検索に使う共通属性はroutine_contentsの実カラムを参照する。
        $representativePackageItemIds = RoutinePackageItem::query()
            ->selectRaw('MIN(id)')
            ->groupBy('routine_content_id');

        $itemQuery = RoutinePackageItem::query()
            ->with(['routineContent'])
            ->whereIn('id', $representativePackageItemIds);

        if ($contentId = trim((string) request('routine_item_content_id', ''))) {
            $numericId = preg_replace('/[^0-9]/', '', $contentId);
            $itemQuery->whereHas('routineContent', function ($query) use ($contentId, $numericId) {
                $query->where('content_code', 'like', "%{$contentId}%");
                if ($numericId !== '') {
                    $query->orWhereRaw("CAST(id AS TEXT) LIKE ?", ['%' . $numericId . '%']);
                }
            });
        }

        if ($keyword = trim((string) request('routine_item_keyword', ''))) {
            $itemQuery->whereHas('routineContent', function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%")
                    ->orWhere('search_tags', 'like', "%{$keyword}%");
            });
        }

        if (($grade = request('routine_item_grade')) && $grade !== 'all') {
            $itemQuery->whereHas('routineContent', function ($query) use ($grade) {
                if ($grade === '未設定') {
                    $query->where(function ($inner) {
                        $inner->whereNull('target_grade')->orWhere('target_grade', '');
                    });
                } elseif ($grade === 'ALL') {
                    $query->where('target_grade', 'ALL');
                } else {
                    $query->where('target_grade', 'like', '%' . $grade . '%');
                }
            });
        }

        if (($difficulty = request('routine_item_difficulty')) && $difficulty !== 'all') {
            $itemQuery->whereHas('routineContent', function ($query) use ($difficulty) {
                if ($difficulty === '未設定') {
                    $query->whereNull('difficulty');
                } else {
                    $query->where('difficulty', (int) $difficulty);
                }
            });
        }

        $routinePackageItems = $itemQuery
            ->orderBy('routine_content_id')
            ->limit(20)
            ->get();

        // 対象学年フィルターは固定コードだけに限定せず、
        // routine_contents.target_grade に実際に保存されている値を候補化する。
        // 「年中〜小2」のような範囲表記も欠落させない。
        $itemGrades = \App\Models\RoutineContent::query()
            ->pluck('target_grade')
            ->map(fn($grade) => ($grade === null || trim((string) $grade) === '') ? '未設定' : trim((string) $grade))
            ->unique()
            ->sortBy(function ($grade) {
                if ($grade === 'ALL') {
                    return '0000';
                }
                if ($grade === '未設定') {
                    return '9999';
                }
                return '5000-' . $grade;
            })
            ->values();

        $itemDifficulties = \App\Models\RoutineContent::query()
            ->pluck('difficulty')
            ->map(fn($difficulty) => $difficulty === null ? '未設定' : (string) $difficulty)
            ->unique()
            ->sortBy(fn($difficulty) => $difficulty === '未設定' ? 999 : (int) $difficulty)
            ->values();

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
            'packageTags' => $packageTags,
            'itemGrades' => $itemGrades,
            'itemDifficulties' => $itemDifficulties,
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

        // 達成必要日数と1日の推奨学習時間は、追加時点のルーティンアイテムマスタ最新値を使用する。
        // routine_package_itemsは過去に作成されたコピー値を保持する場合があるため、最新マスタを優先し、
        // マスタ側が未設定の場合だけパッケージアイテムの値へフォールバックする。
        $masterContent = \Illuminate\Support\Facades\DB::table('routine_contents')
            ->where('id', $source->routine_content_id)
            ->first([
                'name',
                'target_grade',
                'difficulty',
                'search_tags',
                'description',
                'estimated_days',
                'daily_learning_minutes',
            ]);

        $requiredDays = $masterContent?->estimated_days ?? $source->required_days;
        $estimatedMinutes = $masterContent?->daily_learning_minutes ?? $source->estimated_minutes;
        $itemName = $masterContent?->name ?? $source->item_name;
        $targetGrade = $masterContent?->target_grade ?? $source->target_grade;
        $targetLevel = $masterContent?->difficulty !== null
            ? (string) $masterContent->difficulty
            : $source->target_level;
        $tag = $masterContent?->search_tags ?? $source->tag;
        $memo = $masterContent?->description ?? $source->memo;

        $nextOrderNo = ((int) \App\Models\StudentRoutineItem::query()
            ->where('student_routine_id', $targetRoutine->id)
            ->max('order_no')) + 1;

        $createdItem = \App\Models\StudentRoutineItem::create([
            'student_routine_id' => $targetRoutine->id,
            'routine_package_item_id' => $source->id,
            'routine_content_id' => $source->routine_content_id,
            'item_name' => $itemName,
            'target_grade' => $targetGrade,
            'target_level' => $targetLevel,
            'tag' => $tag,
            'completion_type_id' => $source->completion_type_id,
            'target_value' => $source->target_value,
            'estimated_minutes' => $estimatedMinutes,
            'order_no' => $nextOrderNo,
            'is_required' => $source->is_required,
            'is_active' => true,
            'memo' => $memo,
            'required_days' => $requiredDays,
            'start_date' => now()->toDateString(),
        ]);

        $requiredDays = max(1, (int) ($requiredDays ?? 1));

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