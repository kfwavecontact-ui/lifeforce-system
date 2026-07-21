<?php

namespace App\Models;

use App\Enums\BadgeGrantMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * バッジマスタモデル。
 *
 * 関連画面: わくわく > バッジ > バッジ一覧
 * 利用DB: badges（参照・更新）
 */
class Badge extends Model
{
    protected $fillable = [
        'badge_category_id',
        'badge_series_id',
        'code',
        'grant_method',
        'name',
        'level',
        'description',
        'acquisition_message',
        'allow_regrant',
        'notify_on_grant',
        'image_path',
        'locked_image_path',
        'point_reward',
        'is_limited',
        'start_date',
        'end_date',
        'display_order',
        'is_active',
        'created_by',
        'updated_by',
        'condition_operator',
    ];

    protected function casts(): array
    {
        return [
            'grant_method' => BadgeGrantMethod::class,
            'allow_regrant' => 'boolean',
            'notify_on_grant' => 'boolean',
            'is_limited' => 'boolean',
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'point_reward' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BadgeCategory::class, 'badge_category_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(BadgeSeries::class, 'badge_series_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(BadgeRequirement::class);
    }

    public function studentBadges(): HasMany
    {
        return $this->hasMany(StudentBadge::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(StudentBadgeEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
