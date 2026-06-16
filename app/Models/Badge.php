<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = [
        'badge_category_id',
        'badge_series_id',
        'code',
        'name',
        'level',
        'description',
        'image_path',
        'point_reward',
        'is_limited',
        'start_date',
        'end_date',
        'display_order',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(BadgeCategory::class, 'badge_category_id');
    }

    public function series()
    {
        return $this->belongsTo(BadgeSeries::class, 'badge_series_id');
    }

    public function requirements()
    {
        return $this->hasMany(BadgeRequirement::class);
    }

    public function studentBadges()
    {
        return $this->hasMany(StudentBadge::class);
    }
}