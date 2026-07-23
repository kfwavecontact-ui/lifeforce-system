<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RewardItemImage extends Model
{
    protected $fillable = ['reward_item_id','image_path','alt_text','display_order','is_main'];
    protected $casts = ['display_order'=>'integer','is_main'=>'boolean'];
    public function item(): BelongsTo { return $this->belongsTo(RewardItem::class, 'reward_item_id'); }
}
