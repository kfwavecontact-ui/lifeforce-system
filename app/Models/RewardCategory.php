<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardCategory extends Model
{
    protected $fillable = ['name', 'description', 'icon', 'color_code', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(RewardItem::class);
    }
}
