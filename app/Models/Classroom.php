<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = [
        'school_id',
        'code',
        'name',
        'capacity',
        'sort_order',
        'is_active',
    ];

    public function studentClasses()
    {
        return $this->hasMany(StudentClass::class);
    }
}