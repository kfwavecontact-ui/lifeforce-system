<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeLog extends Model
{
    protected $fillable = [
        'student_id',
        'challenge_id',
        'challenge_reservation_id',
        'teacher_id',
        'score',
        'passing_score',
        'result',
        'comment',
        'challenged_at',
    ];

    protected $casts = [
        'challenged_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function reservation()
    {
        return $this->belongsTo(
            ChallengeReservation::class,
            'challenge_reservation_id'
        );
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}