<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutinePackageItem extends Model
{
    protected $fillable = [
        'routine_package_id',
        'routine_content_id',
        'item_name',
        'target_grade',
        'target_level',
        'tag',
        'completion_type_id',
        'target_value',
        'required_days',
        'estimated_minutes',
        'order_no',
        'is_required',
        'memo',
    ];

    public function package()
    {
        return $this->belongsTo(RoutinePackage::class, 'routine_package_id');
    }

    public function routineContent()
    {
        return $this->belongsTo(RoutineContent::class, 'routine_content_id');
    }

    public function completionType()
    {
        return $this->belongsTo(RoutineCompletionType::class, 'completion_type_id');
    }
}