<?php

namespace App\Models;

use App\Enums\RewardExchangeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 商品交換申請モデル。
 * 関連画面: 商品一覧／申請情報／交換履歴
 * 利用DB: reward_exchange_requests（参照・更新）
 */
class RewardExchangeRequest extends Model
{
    protected $fillable = [
        'request_number','student_id','reward_item_id','quantity','request_points','returned_points','status','status_name',
        'delivery_method','delivery_status','tracking_number','inventory_status','point_status','requested_at','approved_at',
        'prepared_at','shipped_at','delivered_at','rejected_at','cancelled_at','handled_by','cancelled_by','note','rejection_reason',
        'cancellation_reason','point_transaction_id','return_point_transaction_id','account_transaction_id',
    ];
    protected $casts = [
        'quantity'=>'integer','request_points'=>'integer','returned_points'=>'integer','requested_at'=>'datetime','approved_at'=>'datetime',
        'prepared_at'=>'datetime','shipped_at'=>'datetime','delivered_at'=>'datetime','rejected_at'=>'datetime','cancelled_at'=>'datetime',
    ];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function item(): BelongsTo { return $this->belongsTo(RewardItem::class, 'reward_item_id')->withTrashed(); }
    public function handler(): BelongsTo { return $this->belongsTo(User::class, 'handled_by'); }
    public function events(): HasMany { return $this->hasMany(RewardExchangeEvent::class)->orderBy('occurred_at')->orderBy('id'); }
    public function statusEnum(): RewardExchangeStatus { return RewardExchangeStatus::tryFrom($this->status) ?? RewardExchangeStatus::Requested; }
}
