<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品交換申請の操作履歴モデル。
 * 関連画面: わくわく ＞ 商品交換所 ＞ 申請情報／交換履歴
 * 関連Service: App\Services\Reward\RewardExchangeService
 * 利用DB: reward_exchange_events
 * 利用目的: 誰が、いつ、どの状態へ変更し、ポイント・在庫・会計へ何を行ったかを追跡する。
 * 更新区分: Serviceから登録、画面から参照。
 */
class RewardExchangeEvent extends Model
{
    protected $fillable = [
        'reward_exchange_request_id', 'event_type', 'from_status', 'to_status',
        'title', 'detail', 'actor_id', 'occurred_at', 'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(RewardExchangeRequest::class, 'reward_exchange_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
