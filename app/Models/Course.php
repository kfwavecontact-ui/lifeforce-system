<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * コースに紐づく料金一覧
     */
    public function prices(): HasMany
    {
        return $this->hasMany(CoursePrice::class);
    }
}
