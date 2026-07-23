<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ポイント交換所で使用する商品マスタ。
 *
 * 関連画面:
 * - システム ＞ 設定 ＞ ポイント商品
 * - わくわく ＞ 商品交換所
 *
 * 主な利用テーブル:
 * - reward_items（商品本体）
 * - reward_categories（ポイント商品カテゴリ）
 * - reward_item_stocks（実在庫・予約在庫）
 * - reward_item_images（複数画像）
 */
class RewardItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reward_category_id', 'code', 'name', 'description', 'required_points', 'cost_price',
        'is_stock_managed', 'stock_quantity', 'stock_alert_quantity', 'image_url', 'sort_order', 'is_active', 'publication_status',
        'published_at', 'publication_ended_at',
        'is_recommended', 'is_new', 'is_limited',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'required_points' => 'integer',
        'cost_price' => 'integer',
        'is_stock_managed' => 'boolean',
        'is_active' => 'boolean',
        'is_recommended' => 'boolean',
        'is_new' => 'boolean',
        'is_limited' => 'boolean',
        'published_at' => 'datetime',
        'publication_ended_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RewardCategory::class, 'reward_category_id');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(RewardItemStock::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RewardItemImage::class)->orderBy('display_order');
    }

    public function exchangeRequests(): HasMany
    {
        return $this->hasMany(RewardExchangeRequest::class);
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(RewardItemImage::class)->where('is_main', true);
    }
}
