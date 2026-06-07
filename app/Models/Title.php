<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image_url',
        'rarity',
        'display_order',
        'is_active',
    ];

    public function studentTitles()
    {
        return $this->hasMany(StudentTitle::class);
    }
}