<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTitle extends Model
{
    protected $fillable = [
        'student_id',
        'title_id',
        'acquired_at',
        'is_equipped',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function title()
    {
        return $this->belongsTo(Title::class);
    }
}