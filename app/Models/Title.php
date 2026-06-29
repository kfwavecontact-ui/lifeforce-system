<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image_path',
        'rarity',
        'point_reward',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function studentTitles()
    {
        return $this->hasMany(StudentTitle::class);
    }

    public function tags()
    {
        return $this->belongsToMany(
            TitleTag::class,
            'title_tag_relations',
            'title_id',
            'title_tag_id'
        );
    }

    public function events()
    {
        return $this->belongsToMany(
            Event::class,
            'title_event_relations',
            'title_id',
            'event_id'
        );
    }
}