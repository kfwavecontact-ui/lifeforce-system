<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'default_enabled',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'default_enabled' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roleSettings(): HasMany
    {
        return $this->hasMany(RoleNotificationSetting::class);
    }
}