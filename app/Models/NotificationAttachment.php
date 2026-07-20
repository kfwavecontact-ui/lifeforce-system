<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一斉通知の添付ファイルモデル。
 *
 * ファイル本体ではなく、Storage上のキー・元ファイル名・容量・
 * MIMEタイプ・表示順を管理します。
 */
class NotificationAttachment extends Model
{
    protected $fillable = [
        'notification_id',
        'original_name',
        'storage_key',
        'mime_type',
        'size_bytes',
        'sort_order',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
