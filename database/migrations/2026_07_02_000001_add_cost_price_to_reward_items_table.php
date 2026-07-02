<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_items', function (Blueprint $table) {
            if (! Schema::hasColumn('reward_items', 'cost_price')) {
                $table->integer('cost_price')->default(0)->after('required_points')->comment('商品原価（円）');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reward_items', function (Blueprint $table) {
            if (Schema::hasColumn('reward_items', 'cost_price')) {
                $table->dropColumn('cost_price');
            }
        });
    }
};
