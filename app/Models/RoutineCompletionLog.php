<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutineCompletionLog extends Model
{
    protected $fillable = [
        'student_id',
        'student_routine_id',
        'student_routine_item_id',
        'completed_on',
        'actual_minutes',
        'actual_count',
        'actual_accuracy',
        'point_amount',
        'is_completed',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    public function routineItem()
    {
        return $this->belongsTo(
            StudentRoutineItem::class,
            'student_routine_item_id'
        );
    }
}