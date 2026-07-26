<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutineContent extends Model
{
    /**
     * 共通ルーティンアイテムに設定された標準の達成条件。
     */
    public function defaultCompletionType()
    {
        return $this->belongsTo(RoutineCompletionType::class, 'default_completion_type_id');
    }
}
