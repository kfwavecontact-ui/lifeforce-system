<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TitleTag extends Model
{
    protected $fillable = [
        'name',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function titles()
    {
        return $this->belongsToMany(
            Title::class,
            'title_tag_relations',
            'title_tag_id',
            'title_id'
        );
    }
}