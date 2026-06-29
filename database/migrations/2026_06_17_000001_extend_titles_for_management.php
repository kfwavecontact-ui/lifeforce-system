<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('titles', function (Blueprint $table) {
            $table->integer('point_reward')->default(0)->after('rarity');
        });

        Schema::create('title_tag_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('title_id');
            $table->unsignedBigInteger('title_tag_id');
            $table->timestamps();

            $table->unique(['title_id', 'title_tag_id']);
            $table->index('title_id');
            $table->index('title_tag_id');
        });

        Schema::create('title_event_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('title_id');
            $table->unsignedBigInteger('event_id');
            $table->timestamps();

            $table->unique(['title_id', 'event_id']);
            $table->index('title_id');
            $table->index('event_id');
        });

        DB::table('title_tags')->insert([
            ['name' => '脳開発', 'display_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '将棋', 'display_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '資格', 'display_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'イベント', 'display_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '継続', 'display_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '努力', 'display_order' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '初心者', 'display_order' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '中級者', 'display_order' => 8, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '上級者', 'display_order' => 9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '限定', 'display_order' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '全国', 'display_order' => 11, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '特別', 'display_order' => 12, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('title_event_relations');
        Schema::dropIfExists('title_tag_relations');

        Schema::table('titles', function (Blueprint $table) {
            $table->dropColumn('point_reward');
        });

        Schema::dropIfExists('title_tags');
    }
};