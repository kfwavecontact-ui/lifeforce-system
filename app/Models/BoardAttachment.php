<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 掲示板へ添付されたファイルを管理するモデル。 */
class BoardAttachment extends Model
{
    protected $fillable = ['board_post_id', 'original_name', 'storage_key', 'mime_type', 'size_bytes', 'sort_order'];

    public function boardPost(): BelongsTo
    {
        return $this->belongsTo(BoardPost::class);
    }
}
