<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TitleCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'display_order',
        'is_active',
    ];

    public function series()
    {
        return $this->hasMany(TitleSeries::class, 'title_category_id');
    }

    public function titles()
    {
        return $this->hasMany(Title::class, 'title_category_id');
    }
}