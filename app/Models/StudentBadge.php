<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentBadge extends Model
{
    protected $fillable = [
        'student_id',
        'badge_id',
        'acquired_at',
        'is_displayed',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }
}