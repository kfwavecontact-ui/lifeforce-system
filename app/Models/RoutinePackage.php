<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutinePackage extends Model
{
    protected $fillable = [
        'name',
        'description',
        'icon_type',
        'icon_url',
        'theme_color',
        'category',
        'target_grade',
        'target_level',
        'tag',
        'sort_order',
        'is_active',
        'created_by',
    ];

    public function items()
    {
        return $this->hasMany(RoutinePackageItem::class, 'routine_package_id')
            ->orderBy('order_no');
    }
}