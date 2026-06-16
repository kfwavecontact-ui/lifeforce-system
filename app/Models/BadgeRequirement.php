<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeRequirement extends Model
{
    protected $fillable = [
        'badge_id',
        'badge_requirement_type_id',
        'requirement_type',
        'requirement_value',
    ];

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    public function requirementType()
    {
        return $this->belongsTo(BadgeRequirementType::class, 'badge_requirement_type_id');
    }
}