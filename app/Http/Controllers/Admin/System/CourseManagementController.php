<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CoursePrice;
use App\Models\StudentCourseContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseManagementController extends Controller
{
    public function index()
    {
        return view('admin.system.courses.index');
    }

    public function list(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $status = $request->query('status', 'all');

        $query = CoursePrice::query()
            ->with('course')
            ->join('courses', 'course_prices.course_id', '=', 'courses.id')
            ->select('course_prices.*')
            ->orderBy('course_prices.sort_order')
            ->orderBy('course_prices.id');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('courses.code', 'like', "%{$keyword}%")
                    ->orWhere('courses.name', 'like', "%{$keyword}%")
                    ->orWhere('course_prices.attendance_type', 'like', "%{$keyword}%");
            });
        }

        if ($status === 'active') {
            $query->where('course_prices.is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('course_prices.is_active', false);
        }

        $rows = $query->get();

        $contractCounts = StudentCourseContract::query()
            ->select('course_price_id', DB::raw('count(*) as count'))
            ->where('is_active', true)
            ->groupBy('course_price_id')
            ->pluck('count', 'course_price_id');

        return response()->json([
            'rows' => $rows->map(function (CoursePrice $price) use ($contractCounts) {
                return [
                    'id' => $price->id,
                    'course_id' => $price->course_id,
                    'code' => $price->course?->code,
                    'course_name' => $price->course?->name,
                    'attendance_type' => $price->attendance_type,
                    'monthly_fee' => $price->monthly_fee,
                    'sort_order' => $price->sort_order,
                    'is_recommended' => (bool) $price->course?->is_recommended,
                    'is_active' => (bool) $price->is_active,
                    'contract_count' => (int) ($contractCounts[$price->id] ?? 0),
                    'description' => $price->course?->description,
                ];
            }),
            'summary' => [
                'total' => CoursePrice::count(),
                'active' => CoursePrice::where('is_active', true)->count(),
                'inactive' => CoursePrice::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'code' => ['required_without:course_id', 'nullable', 'string', 'max:255', 'unique:courses,code'],
            'course_name' => ['required_without:course_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attendance_type' => ['required', 'string', 'max:255'],
            'monthly_fee' => ['required', 'integer', 'min:0'],
            'is_recommended' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            if (!empty($validated['course_id'])) {
                $course = Course::findOrFail($validated['course_id']);

                $course->update([
                    'description' => $validated['description'] ?? $course->description,
                    'is_recommended' => (bool) ($validated['is_recommended'] ?? false),
                ]);
            } else {
                $course = Course::create([
                    'code' => $validated['code'],
                    'name' => $validated['course_name'],
                    'sort_order' => (Course::max('sort_order') ?? 0) + 1,
                    'description' => $validated['description'] ?? null,
                    'is_recommended' => (bool) ($validated['is_recommended'] ?? false),
                    'is_active' => true,
                ]);
            }

            CoursePrice::create([
                'course_id'       => $course->id,
                'attendance_type' => $validated['attendance_type'],
                'monthly_fee'     => $validated['monthly_fee'],
                'sort_order' => (CoursePrice::max('sort_order') ?? 0) + 1,
                'is_active'       => (bool) ($validated['is_active'] ?? false),
            ]);
        });

        return response()->json(['message' => '保存しました。']);
    }

    public function update(Request $request, CoursePrice $coursePrice)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'code')->ignore($coursePrice->course_id),
            ],
            'course_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'attendance_type' => ['required', 'string', 'max:255'],
            'monthly_fee' => ['required', 'integer', 'min:0'],
            'is_recommended' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated, $coursePrice) {
            $coursePrice->course->update([
                'code' => $validated['code'],
                'name' => $validated['course_name'],
                'description' => $validated['description'] ?? null,
                'is_recommended' => (bool) ($validated['is_recommended'] ?? false),
            ]);

            $coursePrice->update([
                'attendance_type' => $validated['attendance_type'],
                'monthly_fee'     => $validated['monthly_fee'],
                'sort_order'      => $coursePrice->sort_order ?? 999,
                'is_active'       => (bool) ($validated['is_active'] ?? false),
            ]);
        });

        return response()->json(['message' => '更新しました。']);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:course_prices,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                CoursePrice::whereKey($item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return response()->json(['message' => '並び順を更新しました。']);
    }
}