<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_lesson_reservation_id',
        'student_id',
        'lesson_session_id',
        'attendance_status',
        'checked_in_at',
        'checked_out_at',
        'late_minutes',
        'early_leave_minutes',
        'recorded_by_user_id',
        'note',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}