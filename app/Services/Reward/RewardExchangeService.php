<?php

namespace App\Services\Reward;

use App\Enums\RewardExchangeStatus;
use App\Enums\AccountTransactionSourceType;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\PointTransaction;
use App\Models\RewardExchangeEvent;
use App\Models\RewardExchangeRequest;
use App\Models\RewardItem;
use App\Models\RewardItemStock;
use App\Models\StudentPointBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * 商品交換のポイント・在庫・状態・会計を一つのトランザクション境界で管理します。
 * 利用DB: reward_exchange_requests / student_point_balances / point_transactions / reward_item_stocks / account_transactions
 * 更新区分: 更新。lockForUpdateと処理済みIDにより二重処理を防止します。
 */
class RewardExchangeService
{
    public function request(int $studentId, int $itemId, int $quantity, string $deliveryMethod, ?string $note, ?int $actorId): RewardExchangeRequest
    {
        if (!$actorId) {
            throw new AuthorizationException('交換申請の実行者を確認できません。');
        }

        return DB::transaction(function () use ($studentId,$itemId,$quantity,$deliveryMethod,$note,$actorId) {
            $item=RewardItem::query()->withTrashed()->lockForUpdate()->findOrFail($itemId);
            $stock=RewardItemStock::query()->where('reward_item_id',$itemId)->lockForUpdate()->first();
            $balance = StudentPointBalance::query()->firstOrCreate(
                ['student_id' => $studentId],
                ['current_points' => 0, 'total_earned_points' => 0, 'total_used_points' => 0]
            );
            $balance = StudentPointBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            $total=$item->required_points*$quantity;
            $this->assertExchangeable($item,$stock,$balance->current_points,$studentId,$quantity);

            $request=RewardExchangeRequest::create([
                'student_id'=>$studentId,'reward_item_id'=>$itemId,'quantity'=>$quantity,'request_points'=>$total,
                'status'=>RewardExchangeStatus::Requested->value,'status_name'=>RewardExchangeStatus::Requested->label(),
                'delivery_method'=>$deliveryMethod,'delivery_status'=>'waiting','inventory_status'=>$item->is_stock_managed?'reserved':'unmanaged',
                'point_status'=>'deducted','requested_at'=>now(),'handled_by'=>$actorId,'note'=>$note,
            ]);
            $request->update(['request_number'=>'REX-'.now()->format('Ymd').'-'.str_pad((string)$request->id,6,'0',STR_PAD_LEFT)]);

            $newBalance=$balance->current_points-$total;
            $point=PointTransaction::create(['student_id'=>$studentId,'event_type'=>'reward_exchange','event_type_name'=>'ポイント商品交換','point_rule_code'=>'reward_exchange','points'=>-$total,'balance_after'=>$newBalance,'reason'=>'ポイント商品交換申請：'.$item->name,'related_id'=>$request->id,'occurred_at'=>now()]);
            $balance->update(['current_points'=>$newBalance,'total_used_points'=>$balance->total_used_points+$total]);
            if($item->is_stock_managed && $stock){$stock->increment('reserved_quantity',$quantity);}
            $request->update(['point_transaction_id'=>$point->id]);
            $this->recordEvent($request, 'requested', null, RewardExchangeStatus::Requested->value, '申請', 'ポイントを減算し、在庫を予約しました。', $actorId, [
                'points' => $total,
                'quantity' => $quantity,
                'point_transaction_id' => $point->id,
            ]);
            return $request->fresh();
        },3);
    }

    public function transition(RewardExchangeRequest $exchange, RewardExchangeStatus $next, array $data, ?int $actorId): RewardExchangeRequest
    {
        if (!$actorId) {
            throw new AuthorizationException('申請処理の実行者を確認できません。');
        }

        return DB::transaction(function () use ($exchange,$next,$data,$actorId) {
            $exchange=RewardExchangeRequest::query()->lockForUpdate()->findOrFail($exchange->id);
            $current=$exchange->statusEnum();
            if(!$current->canTransitionTo($next)){throw ValidationException::withMessages(['status'=>'現在の状態からその操作は実行できません。']);}
            $item=RewardItem::withTrashed()->findOrFail($exchange->reward_item_id);
            $stock=RewardItemStock::where('reward_item_id',$item->id)->lockForUpdate()->first();
            $updates=['status'=>$next->value,'status_name'=>$next->label(),'handled_by'=>$actorId,'delivery_method'=>$data['delivery_method']??$exchange->delivery_method,'tracking_number'=>$data['tracking_number']??$exchange->tracking_number,'note'=>$data['note']??$exchange->note];
            if($next===RewardExchangeStatus::Approved){$updates['approved_at']=now();}
            if($next===RewardExchangeStatus::Preparing){$updates['prepared_at']=now();$updates['delivery_status']='preparing';}
            if($next===RewardExchangeStatus::Shipped){$updates['shipped_at']=now();$updates['delivery_status']='shipped';}
            if(in_array($next,[RewardExchangeStatus::Rejected,RewardExchangeStatus::Cancelled],true)){
                $this->refund($exchange,$item,$stock,$actorId);
                $updates[$next===RewardExchangeStatus::Rejected?'rejected_at':'cancelled_at']=now();
                $updates[$next===RewardExchangeStatus::Rejected?'rejection_reason':'cancellation_reason']=$data['reason']??null;
                $updates['inventory_status']=$item->is_stock_managed?'released':'unmanaged';$updates['point_status']='refunded';$updates['returned_points']=$exchange->request_points;
                if($next===RewardExchangeStatus::Cancelled){$updates['cancelled_by']=$actorId;}
            }
            if($next===RewardExchangeStatus::Delivered){
                if($item->is_stock_managed && $stock){
                    if($stock->reserved_quantity<$exchange->quantity || $stock->stock_quantity<$exchange->quantity){throw ValidationException::withMessages(['stock'=>'確保済み在庫が不足しています。']);}
                    $stock->decrement('reserved_quantity',$exchange->quantity);$stock->decrement('stock_quantity',$exchange->quantity);
                    $item->update(['stock_quantity'=>max(0,$item->stock_quantity-$exchange->quantity)]);
                }
                $updates['delivered_at']=now();$updates['delivery_status']='delivered';$updates['inventory_status']=$item->is_stock_managed?'deducted':'unmanaged';
                $accountId=$this->createAccountTransaction($exchange,$item,$actorId); if($accountId){$updates['account_transaction_id']=$accountId;}
            }
            $exchange->update($updates);
            $detail = match ($next) {
                RewardExchangeStatus::Approved => '申請を承認しました。ポイント・在庫の追加処理はありません。',
                RewardExchangeStatus::Preparing => '受渡準備中へ変更しました。',
                RewardExchangeStatus::Shipped => '発送済へ変更しました。追跡番号: '.($updates['tracking_number'] ?: '-'),
                RewardExchangeStatus::Delivered => '受渡を完了し、在庫確定と会計連携を実行しました。',
                RewardExchangeStatus::Rejected => '申請を却下し、ポイント返還と在庫予約解除を実行しました。理由: '.($updates['rejection_reason'] ?: '-'),
                RewardExchangeStatus::Cancelled => '申請を取消し、ポイント返還と在庫予約解除を実行しました。理由: '.($updates['cancellation_reason'] ?: '-'),
                default => '申請状態を更新しました。',
            };
            $this->recordEvent(
                $exchange,
                $next->value,
                $current->value,
                $next->value,
                $next->label(),
                $detail,
                $actorId,
                [
                    'point_status' => $updates['point_status'] ?? $exchange->point_status,
                    'inventory_status' => $updates['inventory_status'] ?? $exchange->inventory_status,
                    'account_transaction_id' => $updates['account_transaction_id'] ?? $exchange->account_transaction_id,
                    'note' => $updates['note'] ?? null,
                ]
            );
            return $exchange->fresh();
        },3);
    }

    private function assertExchangeable(RewardItem $item, ?RewardItemStock $stock, int $balance, int $studentId, int $quantity): void
    {
        $now=now(); $reasons=[];
        if(!$item->is_active || $item->trashed())$reasons[]='商品が無効です。';
        if($item->publication_status!=='published')$reasons[]='商品が公開されていません。';
        if($item->published_at && $item->published_at->isFuture())$reasons[]='公開開始前です。';
        if($item->publication_ended_at && $item->publication_ended_at->isPast())$reasons[]='公開期間が終了しています。';
        if($balance<$item->required_points*$quantity)$reasons[]='ポイントが不足しています。';
        if($item->is_stock_managed && (!$stock || $stock->available_quantity<$quantity))$reasons[]='交換可能在庫が不足しています。';
        if(RewardExchangeRequest::where('student_id',$studentId)->where('reward_item_id',$item->id)->whereIn('status',['requested','approved','preparing','shipped'])->exists())$reasons[]='同じ商品の未処理申請があります。';
        if($reasons)throw ValidationException::withMessages(['exchange'=>$reasons]);
    }

    private function refund(RewardExchangeRequest $exchange, RewardItem $item, ?RewardItemStock $stock, ?int $actorId): void
    {
        if($exchange->return_point_transaction_id || $exchange->point_status==='refunded')throw ValidationException::withMessages(['status'=>'ポイントはすでに返還済みです。']);
        $balance=StudentPointBalance::where('student_id',$exchange->student_id)->lockForUpdate()->firstOrFail();
        $new=$balance->current_points+$exchange->request_points;
        $point=PointTransaction::create(['student_id'=>$exchange->student_id,'event_type'=>'reward_exchange_refund','event_type_name'=>'ポイント商品交換返還','point_rule_code'=>'reward_exchange_refund','points'=>$exchange->request_points,'balance_after'=>$new,'reason'=>'ポイント商品交換の返還：'.$item->name,'related_id'=>$exchange->id,'occurred_at'=>now()]);
        $balance->update(['current_points'=>$new,'total_used_points'=>max(0,$balance->total_used_points-$exchange->request_points)]);
        if($item->is_stock_managed && $stock && $exchange->inventory_status==='reserved')$stock->decrement('reserved_quantity',min($exchange->quantity,$stock->reserved_quantity));
        $exchange->update(['return_point_transaction_id'=>$point->id]);
    }

    private function createAccountTransaction(RewardExchangeRequest $exchange, RewardItem $item, ?int $actorId): ?int
    {
        $existing=AccountTransaction::whereIn('source_table',[AccountTransactionSourceType::PointExchange->value,'reward_exchange_requests','reward_exchange_request'])->where('source_id',$exchange->id)->first(); if($existing)return $existing->id;
        $category=AccountCategory::whereIn('code',['point_item_cost','point_cost'])->first(); if(!$category)return null;
        $student=DB::table('students')->where('id',$exchange->student_id)->first(); if(!$student)return null;
        return AccountTransaction::create(['scheduled_date'=>today(),'transaction_date'=>today(),'account_category_id'=>$category->id,'transaction_name'=>'ポイント商品費用：'.$item->name,'amount'=>(int)$item->cost_price*$exchange->quantity,'status'=>'confirmed','school_id'=>$student->school_id,'student_id'=>$student->id,'source_table'=>AccountTransactionSourceType::PointExchange,'source_id'=>$exchange->id,'created_by'=>$actorId,'updated_by'=>$actorId,'memo'=>'商品交換所の受渡完了により自動計上'])->id;
    }
    /** 操作履歴を申請単位で保存します。 */
    private function recordEvent(
        RewardExchangeRequest $exchange,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        string $title,
        ?string $detail,
        ?int $actorId,
        array $metadata = []
    ): void {
        RewardExchangeEvent::create([
            'reward_exchange_request_id' => $exchange->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'title' => $title,
            'detail' => $detail,
            'actor_id' => $actorId,
            'occurred_at' => now(),
            'metadata' => $metadata ?: null,
        ]);
    }

}
