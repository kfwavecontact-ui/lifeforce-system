<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLessonReservation extends Model
{
    protected $fillable = [
        'student_id',
        'lesson_session_id',
        'original_reservation_id',
        'reservation_status',
        'reserved_at',
        'cancelled_at',
        'note',
        'is_active',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function lessonSession()
    {
        return $this->belongsTo(LessonSession::class);
    }
}