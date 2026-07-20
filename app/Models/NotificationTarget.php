<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一斉通知の対象条件モデル。
 *
 * 通知作成時の全体・教室・学年・コース・生徒個別と、
 * 生徒・保護者の受信者区分を保存します。
 */
class NotificationTarget extends Model
{
    protected $fillable = [
        'notification_id',
        'target_type',
        'target_id',
        'recipient_type',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
