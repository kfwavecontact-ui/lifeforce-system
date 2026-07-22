<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TitleRequirementType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'display_order',
        'is_active',
    ];

    public function requirements()
    {
        return $this->hasMany(TitleRequirement::class, 'title_requirement_type_id');
    }
}