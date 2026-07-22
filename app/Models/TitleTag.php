<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 称号タグマスタモデル。
 *
 * 関連画面:
 * - システム > 設定 > マスタ管理 > 称号タグ
 * - システム > 設定 > 称号管理
 *
 * 利用DB:
 * - title_tags（参照・更新）
 * - title_tag_relations（参照・更新）
 */
class TitleTag extends Model
{
    protected $fillable = [
        'name',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(
            Title::class,
            'title_tag_relations',
            'title_tag_id',
            'title_id'
        )->withTimestamps();
    }
}
