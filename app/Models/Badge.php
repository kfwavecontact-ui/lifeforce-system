<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = [
        'badge_category_id',
        'name',
        'level',
        'description',
        'image_url',
        'locked_image_url',
        'point_reward',
        'is_limited',
        'start_date',
        'end_date',
        'display_order',
        'is_active',
    ];

    public function studentBadges()
    {
        return $this->hasMany(StudentBadge::class);
    }
}