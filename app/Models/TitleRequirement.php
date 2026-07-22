<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TitleRequirement extends Model
{
    protected $fillable = [
        'title_id',
        'title_requirement_type_id',
        'requirement_type',
        'requirement_value',
    ];

    public function title()
    {
        return $this->belongsTo(Title::class);
    }

    public function requirementType()
    {
        return $this->belongsTo(TitleRequirementType::class, 'title_requirement_type_id');
    }
}