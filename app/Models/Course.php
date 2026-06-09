<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'description',
        'is_recommended',
        'is_active',
    ];

    public function coursePrices()
    {
        return $this->hasMany(CoursePrice::class);
    }
}