<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 教室ショップで販売する商品マスタ。
 *
 * 関連画面: 運営 ＞ 教室会計 ＞ ショップ商品、ショップ売上
 * 利用DB: shop_products（参照・更新）、shop_categories / schools（関連参照）
 * 在庫は既存売上処理との互換性を保つため、当面 shop_products.stock_quantity を正とする。
 */
class ShopProduct extends Model
{
    protected $fillable = [
        'school_id', 'category_id', 'product_code', 'name', 'description',
        'price', 'purchase_price', 'tax_rate', 'stock_quantity', 'is_stock_managed',
        'point_reward', 'point_price', 'barcode', 'image_path', 'is_online',
        'published_at', 'sales_end_at', 'display_order', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'price' => 'integer',
        'purchase_price' => 'integer',
        'tax_rate' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_stock_managed' => 'boolean',
        'point_reward' => 'integer',
        'point_price' => 'integer',
        'is_online' => 'boolean',
        'published_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'category_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
