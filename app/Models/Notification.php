<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 一斉通知本体モデル。
 *
 * 関連画面:
 * - 運営 > 連絡 > 一斉通知
 * - 生徒・保護者ポータル > 通知
 *
 * 利用テーブル:
 * - notifications: 通知本体の参照・登録・更新
 * - notification_targets: 送信対象条件の参照・更新
 * - notification_recipients: 受信者別配信状態の参照・更新
 * - notification_attachments: 添付情報の参照・更新
 */
class Notification extends Model
{
    protected $fillable = [
        'notification_type_id',
        'notification_master_id',
        'created_by_user_id',
        'updated_by_user_id',
        'title',
        'body',
        'is_important',
        'show_login_modal',
        'confirmation_required',
        'board_post_id',
        'link_url',
        'related_table',
        'related_id',
        'scheduled_at',
        'sent_at',
        'expires_at',
        'notification_status',
    ];

    protected $casts = [
        'is_important' => 'boolean',
        'show_login_modal' => 'boolean',
        'confirmation_required' => 'boolean',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function notificationMaster(): BelongsTo
    {
        return $this->belongsTo(NotificationMaster::class);
    }

    public function boardPost(): BelongsTo
    {
        return $this->belongsTo(BoardPost::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NotificationTarget::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NotificationAttachment::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
