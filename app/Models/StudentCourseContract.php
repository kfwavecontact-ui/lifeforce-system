<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCourseContract extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'course_price_id',
        'contract_status',
        'started_at',
        'ended_at',
        'monthly_fee',
        'note',
        'is_active',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function coursePrice()
    {
        return $this->belongsTo(CoursePrice::class);
    }
}