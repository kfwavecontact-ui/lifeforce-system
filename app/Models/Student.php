<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ParentModel;
use App\Models\ParentStudent;
use App\Models\StudentLessonReservation;
use App\Models\StudentBadge;
use App\Models\Badge;
use App\Models\StudentTitle;
use App\Models\Title;
use App\Models\StudentLessonNote;
use App\Models\StudentRoutine;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'grade_id',
        'enrollment_status_id',

        'student_code',

        'last_name',
        'first_name',

        'gender',
        'birthday',
        'school_name',
        'commute_days',
        'remarks',

        'enrolled_at',
        'withdrawn_at',

        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentStudents()
    {
        return $this->hasMany(ParentStudent::class);
    }

    public function parents()
    {
        return $this->belongsToMany(
            ParentModel::class,
            'parent_students',
            'student_id',
            'parent_id'
        )->withPivot([
            'relationship',
            'is_primary'
        ]);
    }

    public function lessonReservations()
    {
        return $this->hasMany(StudentLessonReservation::class);
    }

    public function studentBadges()
    {
        return $this->hasMany(StudentBadge::class);
    }

    public function badges()
    {
        return $this->belongsToMany(
            Badge::class,
            'student_badges',
            'student_id',
            'badge_id'
        )->withPivot([
            'acquired_at',
            'is_displayed'
        ]);
    }

    public function studentTitles()
{
    return $this->hasMany(StudentTitle::class);
}

    public function titles()
    {
        return $this->belongsToMany(
            Title::class,
            'student_titles',
            'student_id',
            'title_id'
        )->withPivot([
            'acquired_at',
            'is_equipped'
        ]);
    }

    public function lessonNotes()
    {
        return $this->hasMany(StudentLessonNote::class)
            ->latest();
    }

    public function routines()
    {
        return $this->hasMany(StudentRoutine::class);
    }
}