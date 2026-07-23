<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ポイント商品を物理削除せず、過去の交換履歴を保持したまま非表示にするため、
 * reward_items に論理削除日時を追加します。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_items', function (Blueprint $table) {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::table('reward_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
