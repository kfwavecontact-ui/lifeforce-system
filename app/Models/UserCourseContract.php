<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCourseContract extends Model
{
    protected $fillable = [
        'user_id',
        'course_price_id',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * 契約ユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 契約料金プラン
     */
    public function coursePrice(): BelongsTo
    {
        return $this->belongsTo(CoursePrice::class);
    }
}
