<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentLessonNote extends Model
{
    protected $fillable = [
        'student_lesson_reservation_id',
        'student_id',
        'lesson_session_id',
        'note_type_id',
        'user_id',
        'body',
        'is_private',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}