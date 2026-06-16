<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeRequirementType extends Model
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
        return $this->hasMany(BadgeRequirement::class, 'badge_requirement_type_id');
    }
}