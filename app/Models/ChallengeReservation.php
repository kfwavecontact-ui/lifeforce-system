<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeReservation extends Model
{
    protected $fillable = [
        'student_id',
        'challenge_id',
        'reserved_at',
        'reservation_status',
        'teacher_id',
        'note',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}