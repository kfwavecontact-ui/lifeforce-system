<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPointBalance extends Model
{
    protected $table = 'student_point_balances';

    protected $guarded = [];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}