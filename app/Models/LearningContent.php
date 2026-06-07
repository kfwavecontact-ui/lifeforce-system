<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningContent extends Model
{
    protected $fillable = [
        'category_code',
        'category_name',
        'name',
        'description',
        'default_completion_type_id',
        'default_required_minutes',
        'default_required_count',
        'default_required_accuracy',
        'default_required_streak',
        'point_amount',
        'sort_order',
        'is_active',
    ];
}