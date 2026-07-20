<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一斉通知の受信者別配信状態モデル。
 *
 * ポータル・メール・LINEなどのチャネルごとに1レコードを作成し、
 * 配信状態、既読日時、確認日時を個別に管理します。
 */
class NotificationRecipient extends Model
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'recipient_type',
        'channel',
        'recipient_address',
        'delivery_status',
        'delivered_at',
        'read_at',
        'confirmed_at',
        'error_message',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
