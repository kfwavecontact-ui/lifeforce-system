<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'display_order',
        'is_active',
    ];

    public function series()
    {
        return $this->hasMany(BadgeSeries::class, 'badge_category_id');
    }

    public function badges()
    {
        return $this->hasMany(Badge::class, 'badge_category_id');
    }
}