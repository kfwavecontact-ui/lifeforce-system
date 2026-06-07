<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentRoutine extends Model
{
    protected $fillable = [
        'student_id',
        'routine_package_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function package()
    {
        return $this->belongsTo(RoutinePackage::class, 'routine_package_id');
    }

    public function items()
    {
        return $this->hasMany(StudentRoutineItem::class)
            ->orderBy('order_no');
    }
}