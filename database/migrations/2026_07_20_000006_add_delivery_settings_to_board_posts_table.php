<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_posts', function (Blueprint $table) {
            $table->boolean('is_listed')->default(true)->after('is_pinned');
            $table->boolean('is_notified')->default(false)->after('is_listed');
            $table->string('share_type', 20)->default('all')->after('is_notified');
            $table->index(['share_type', 'is_listed', 'is_notified'], 'board_posts_delivery_index');
        });
    }

    public function down(): void
    {
        Schema::table('board_posts', function (Blueprint $table) {
            $table->dropIndex('board_posts_delivery_index');
            $table->dropColumn(['is_listed', 'is_notified', 'share_type']);
        });
    }
};
