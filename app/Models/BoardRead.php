<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 生徒・保護者の既読履歴モデル。 */
class BoardRead extends Model
{
    protected $fillable = ['board_post_id', 'user_id', 'read_at', 'confirmed_at'];

    protected $casts = ['read_at' => 'datetime', 'confirmed_at' => 'datetime'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BoardPost::class, 'board_post_id');
    }
}
