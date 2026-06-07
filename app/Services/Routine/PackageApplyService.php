<?php

namespace App\Services\Routine;

use Illuminate\Support\Facades\DB;

class PackageApplyService
{
    public function apply(int $studentId, int $routinePackageId, ?int $createdBy = null): int
    {
        return DB::transaction(function () use ($studentId, $routinePackageId, $createdBy) {
            $package = DB::table('routine_packages')
                ->where('id', $routinePackageId)
                ->where('is_active', true)
                ->first();

            if (!$package) {
                throw new \Exception('ルーティンパッケージが見つかりません。');
            }

            $studentRoutineId = DB::table('student_routines')->insertGetId([
                'student_id' => $studentId,
                'routine_package_id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $items = DB::table('routine_package_items')
                ->where('routine_package_id', $package->id)
                ->orderBy('order_no')
                ->get();

            foreach ($items as $item) {
                DB::table('student_routine_items')->insert([
                    'student_routine_id' => $studentRoutineId,
                    'routine_package_item_id' => $item->id,
                    'routine_content_id' => $item->routine_content_id,
                    'item_name' => $item->item_name,
                    'target_grade' => $item->target_grade,
                    'target_level' => $item->target_level,
                    'tag' => $item->tag,
                    'completion_type_id' => $item->completion_type_id,
                    'target_value' => $item->target_value,
                    'required_days' => $item->required_days,
                    'estimated_minutes' => $item->estimated_minutes,
                    'order_no' => $item->order_no,
                    'is_required' => $item->is_required,
                    'is_active' => true,
                    'memo' => $item->memo,
                    'start_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $studentRoutineId;
        });
    }
}