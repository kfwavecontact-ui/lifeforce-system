<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentRoutineItem extends Model
{
    protected $fillable = [
        'student_routine_id',
        'routine_package_item_id',
        'routine_content_id',
        'item_name',
        'target_grade',
        'target_level',
        'tag',
        'completion_type_id',
        'target_value',
        'required_days',
        'estimated_minutes',
        'order_no',
        'is_required',
        'is_active',
        'memo',
        'completed_at',
        'self_evaluation_score',
        'self_evaluation_comment',
        'teacher_comment',
        'teacher_comment_user_id',
        'teacher_comment_at',
        'start_date',
    ];

    public function routine()
    {
        return $this->belongsTo(StudentRoutine::class, 'student_routine_id');
    }

    public function routineContent()
    {
        return $this->belongsTo(RoutineContent::class, 'routine_content_id');
    }

    public function completionType()
    {
        return $this->belongsTo(RoutineCompletionType::class, 'completion_type_id');
    }

    public function dailyStatuses()
    {
        return $this->hasMany(StudentRoutineDailyStatus::class, 'student_routine_item_id');
    }
}