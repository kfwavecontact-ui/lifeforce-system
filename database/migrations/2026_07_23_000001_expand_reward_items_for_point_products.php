<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_items', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique();
            $table->string('publication_status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('publication_ended_at')->nullable()->index();
            $table->unsignedInteger('exchange_limit_per_student')->nullable();
            $table->unsignedInteger('exchange_limit_per_month')->nullable();
            $table->boolean('is_recommended')->default(false)->index();
            $table->boolean('is_new')->default(false)->index();
            $table->boolean('is_limited')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('reward_item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_item_id')->unique()->constrained('reward_items')->cascadeOnDelete();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('alert_quantity')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reward_item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_item_id')->constrained('reward_items')->cascadeOnDelete();
            $table->string('image_path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('display_order')->default(1);
            $table->boolean('is_main')->default(false)->index();
            $table->timestamps();
            $table->index(['reward_item_id', 'display_order']);
        });

        DB::table('reward_items')->orderBy('id')->each(function ($item) {
            DB::table('reward_item_stocks')->insert([
                'reward_item_id' => $item->id,
                'stock_quantity' => max(0, (int) $item->stock_quantity),
                'reserved_quantity' => 0,
                'alert_quantity' => max(0, (int) $item->stock_alert_quantity),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($item->image_url)) {
                DB::table('reward_item_images')->insert([
                    'reward_item_id' => $item->id,
                    'image_path' => $item->image_url,
                    'alt_text' => $item->name,
                    'display_order' => 1,
                    'is_main' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('reward_items')->where('id', $item->id)->update([
                'code' => 'RWD-' . str_pad((string) $item->id, 4, '0', STR_PAD_LEFT),
                'publication_status' => $item->is_active ? 'published' : 'draft',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_item_images');
        Schema::dropIfExists('reward_item_stocks');

        Schema::table('reward_items', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'code', 'publication_status', 'published_at', 'publication_ended_at',
                'exchange_limit_per_student', 'exchange_limit_per_month',
                'is_recommended', 'is_new', 'is_limited', 'created_by', 'updated_by',
            ]);
        });
    }
};
