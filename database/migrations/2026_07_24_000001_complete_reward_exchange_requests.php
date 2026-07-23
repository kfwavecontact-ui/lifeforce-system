<?php

use App\Enums\RewardExchangeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 商品交換所3画面に必要な追跡項目と重複防止用索引を追加します。既存行は削除しません。 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->string('request_number', 40)->nullable()->unique();
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('returned_points')->default(0);
            $table->string('delivery_method', 20)->default('classroom');
            $table->string('delivery_status', 20)->default('waiting');
            $table->string('tracking_number', 100)->nullable();
            $table->string('inventory_status', 20)->default('unreserved');
            $table->string('point_status', 20)->default('unprocessed');
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('point_transaction_id')->nullable()->constrained('point_transactions')->nullOnDelete();
            $table->foreignId('return_point_transaction_id')->nullable()->constrained('point_transactions')->nullOnDelete();
            $table->foreignId('account_transaction_id')->nullable()->constrained('account_transactions')->nullOnDelete();
            $table->index(['status','requested_at']);
            $table->index(['student_id','reward_item_id','status']);
        });

        DB::table('reward_exchange_requests')->orderBy('id')->each(function ($row) {
            $status = match ($row->status) { 'delivered' => 'delivered', 'rejected' => 'rejected', default => $row->status ?: 'requested' };
            $enum = RewardExchangeStatus::tryFrom($status) ?? RewardExchangeStatus::Requested;
            DB::table('reward_exchange_requests')->where('id',$row->id)->update([
                'request_number' => $row->request_number ?? ('REX-'.str_pad((string)$row->id,8,'0',STR_PAD_LEFT)),
                'status' => $enum->value, 'status_name' => $enum->label(),
                'inventory_status' => $enum === RewardExchangeStatus::Delivered ? 'deducted' : ($enum->isOpen() ? 'reserved' : 'released'),
                'point_status' => in_array($enum,[RewardExchangeStatus::Rejected,RewardExchangeStatus::Cancelled],true) ? 'refunded' : 'deducted',
                'delivery_status' => $enum === RewardExchangeStatus::Delivered ? 'delivered' : 'waiting',
            ]);
        });
    }
    public function down(): void
    {
        Schema::table('reward_exchange_requests', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']); $table->dropForeign(['point_transaction_id']);
            $table->dropForeign(['return_point_transaction_id']); $table->dropForeign(['account_transaction_id']);
            $table->dropColumn(['request_number','quantity','returned_points','delivery_method','delivery_status','tracking_number','inventory_status','point_status','prepared_at','shipped_at','cancelled_at','cancelled_by','rejection_reason','cancellation_reason','point_transaction_id','return_point_transaction_id','account_transaction_id']);
        });
    }
};
