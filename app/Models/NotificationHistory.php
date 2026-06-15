<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationHistory extends Model
{
    protected $fillable = [
        'notification_master_id',

        'sender_user_id',
        'sender_name',
        'sender_role',

        'recipient_user_id',
        'recipient_name',
        'recipient_role',

        'channel',

        'title',
        'body',

        'status',

        'sent_at',
        'read_at',

        'related_type',
        'related_id',

        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function notificationMaster(): BelongsTo
    {
        return $this->belongsTo(NotificationMaster::class);
    }
}