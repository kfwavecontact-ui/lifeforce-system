<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ショップ商品カテゴリマスタ。
 *
 * 関連画面: 運営 ＞ 教室会計 ＞ ショップ商品
 * 利用DB: shop_categories（参照）、shop_products（関連参照）
 */
class ShopCategory extends Model
{
    protected $fillable = ['name', 'description', 'display_order', 'is_active'];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(ShopProduct::class, 'category_id');
    }
}
