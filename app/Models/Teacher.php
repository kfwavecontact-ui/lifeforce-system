<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'employment_type_id',
        'teacher_status_id',
        'teacher_code',
        'last_name',
        'first_name',
        'phone_number',
        'hire_date',
        'resignation_date',
        'note',
        'is_active',
    ];

    public function studentTeachers()
    {
        return $this->hasMany(StudentTeacher::class);
    }
}