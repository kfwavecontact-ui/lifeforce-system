<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursePrice extends Model
{
    protected $fillable = [
        'course_id',
        'attendance_type',
        'monthly_fee',
        'sort_order',
        'is_active',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}