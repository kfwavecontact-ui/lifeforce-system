<?php

namespace App\Models;

use App\Enums\TitleGrantMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 称号マスタモデル。
 *
 * 関連画面:
 * - システム > 設定 > 称号管理
 * - わくわく > 称号 > 称号一覧
 * - わくわく > 称号 > 獲得履歴
 *
 * 利用DB:
 * - titles（参照・更新）
 * - title_tags / title_tag_relations（参照・更新）
 * - events / title_event_relations（参照・更新）
 * - title_requirements（参照・更新）
 * - student_titles（参照）
 * - title_histories（参照）
 */
class Title extends Model
{
    protected $fillable = [
        'title_category_id',
        'title_series_id',
        'code',
        'grant_method',
        'name',
        'level',
        'rarity',
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
            'grant_method' => TitleGrantMethod::class,
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
        return $this->belongsTo(TitleCategory::class, 'title_category_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(TitleSeries::class, 'title_series_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(TitleRequirement::class);
    }

    /**
     * 称号に設定されたタグ。
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            TitleTag::class,
            'title_tag_relations',
            'title_id',
            'title_tag_id'
        )->withTimestamps();
    }

    /**
     * 称号の対象イベント。
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(
            Event::class,
            'title_event_relations',
            'title_id',
            'event_id'
        )->withTimestamps();
    }

    public function studentTitles(): HasMany
    {
        return $this->hasMany(StudentTitle::class);
    }

    /**
     * 称号の付与・取り外し・再付与履歴。
     */
    public function histories(): HasMany
    {
        return $this->hasMany(TitleHistory::class)->orderBy('event_at');
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
