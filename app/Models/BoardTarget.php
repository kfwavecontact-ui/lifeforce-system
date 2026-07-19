<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 公開対象モデル。target_type と target_id の組み合わせで対象を表現します。 */
class BoardTarget extends Model
{
    protected $fillable = ['board_post_id', 'target_type', 'target_id'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BoardPost::class, 'board_post_id');
    }
}
