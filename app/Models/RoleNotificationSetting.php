<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleNotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_master_id',
        'role',
        'portal_enabled',
        'email_enabled',
        'line_enabled',
        'push_enabled',
        'is_active',
    ];

    protected $casts = [
        'portal_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'line_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function notificationMaster(): BelongsTo
    {
        return $this->belongsTo(NotificationMaster::class);
    }
}