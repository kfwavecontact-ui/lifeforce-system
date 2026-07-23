<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 商品交換申請の操作履歴テーブルを追加します。
 * 既存申請は削除・更新せず、現在保持している日時から初期履歴だけを補完します。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reward_exchange_events')) {
            Schema::create('reward_exchange_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reward_exchange_request_id')->constrained('reward_exchange_requests')->cascadeOnDelete();
                $table->string('event_type', 40);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->string('title', 120);
                $table->text('detail')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('occurred_at');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['reward_exchange_request_id', 'occurred_at'], 'reward_exchange_events_request_time_idx');
                $table->index(['event_type', 'occurred_at'], 'reward_exchange_events_type_time_idx');
            });
        }

        $now = now();
        DB::table('reward_exchange_requests')->orderBy('id')->chunkById(100, function ($rows) use ($now): void {
            foreach ($rows as $row) {
                $events = [
                    ['type' => 'requested', 'title' => '申請', 'at' => $row->requested_at, 'detail' => 'ポイント減算・在庫予約'],
                    ['type' => 'approved', 'title' => '承認', 'at' => $row->approved_at, 'detail' => '申請を承認'],
                    ['type' => 'preparing', 'title' => '準備', 'at' => $row->prepared_at ?? null, 'detail' => '受渡準備を開始'],
                    ['type' => 'shipped', 'title' => '発送', 'at' => $row->shipped_at ?? null, 'detail' => $row->tracking_number ?? null],
                    ['type' => 'delivered', 'title' => '受渡完了', 'at' => $row->delivered_at, 'detail' => '在庫確定・会計連携'],
                    ['type' => 'rejected', 'title' => '却下', 'at' => $row->rejected_at, 'detail' => $row->rejection_reason ?? null],
                    ['type' => 'cancelled', 'title' => '取消', 'at' => $row->cancelled_at ?? null, 'detail' => $row->cancellation_reason ?? null],
                ];

                foreach ($events as $event) {
                    if (!$event['at']) {
                        continue;
                    }
                    $exists = DB::table('reward_exchange_events')
                        ->where('reward_exchange_request_id', $row->id)
                        ->where('event_type', $event['type'])
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    DB::table('reward_exchange_events')->insert([
                        'reward_exchange_request_id' => $row->id,
                        'event_type' => $event['type'],
                        'from_status' => null,
                        'to_status' => $event['type'],
                        'title' => $event['title'],
                        'detail' => $event['detail'],
                        'actor_id' => $row->handled_by,
                        'occurred_at' => $event['at'],
                        'metadata' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_exchange_events');
    }
};
