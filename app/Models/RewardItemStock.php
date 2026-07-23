<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RewardItemStock extends Model
{
    protected $fillable = ['reward_item_id','stock_quantity','reserved_quantity','alert_quantity','updated_by'];
    protected $casts = ['stock_quantity'=>'integer','reserved_quantity'=>'integer','alert_quantity'=>'integer'];
    public function item(): BelongsTo { return $this->belongsTo(RewardItem::class, 'reward_item_id'); }
    public function getAvailableQuantityAttribute(): int { return max(0, $this->stock_quantity - $this->reserved_quantity); }
}
