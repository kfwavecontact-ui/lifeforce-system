<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoursePrice extends Model
{
    protected $fillable = [
        'course_id',
        'plan_type',
        'price',
        'is_recommended',
    ];

    /**
     * 紐づくコース
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * 契約一覧
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(UserCourseContract::class);
    }
}
