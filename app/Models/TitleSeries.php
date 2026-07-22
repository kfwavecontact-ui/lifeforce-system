<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TitleSeries extends Model
{
    protected $table = 'title_series';

    protected $fillable = [
        'title_category_id',
        'name',
        'description',
        'display_order',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(TitleCategory::class, 'title_category_id');
    }

    public function titles()
    {
        return $this->hasMany(Title::class, 'title_series_id');
    }
}