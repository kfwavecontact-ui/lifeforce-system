<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeSeries extends Model
{
    protected $table = 'badge_series';

    protected $fillable = [
        'badge_category_id',
        'name',
        'description',
        'display_order',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(BadgeCategory::class, 'badge_category_id');
    }

    public function badges()
    {
        return $this->hasMany(Badge::class, 'badge_series_id');
    }
}