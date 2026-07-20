<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 掲示板投稿モデル。
 *
 * 運営画面の掲示板に表示する投稿本体を管理します。
 * 公開対象は board_targets、既読情報は board_reads に分離します。
 */
class BoardPost extends Model
{
    protected $fillable = [
        'category', 'title', 'body', 'status', 'publish_from', 'publish_to',
        'is_important', 'is_pinned', 'is_listed', 'is_notified', 'notify_at', 'notified_at', 'requires_confirmation', 'share_type', 'attachment_name', 'attachment_path',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'publish_from' => 'datetime',
        'publish_to' => 'datetime',
        'is_important' => 'boolean',
        'is_pinned' => 'boolean',
        'is_listed' => 'boolean',
        'is_notified' => 'boolean',
        'notify_at' => 'datetime',
        'notified_at' => 'datetime',
        'requires_confirmation' => 'boolean',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(BoardTarget::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(BoardRead::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BoardAttachment::class)->orderBy('sort_order')->orderBy('id');
    }
}
