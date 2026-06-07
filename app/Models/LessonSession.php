<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonSession extends Model
{
    protected $fillable = [
        'lesson_schedule_id',
        'calendar_event_id',
        'school_id',
        'classroom_id',
        'course_id',
        'lesson_type_id',
        'teacher_user_id',
        'title',
        'lesson_date',
        'start_at',
        'end_at',
        'lesson_status',
        'max_students',
        'note',
        'is_cancelled',
        'is_active',
    ];

    public function reservations()
    {
        return $this->hasMany(StudentLessonReservation::class);
    }
}