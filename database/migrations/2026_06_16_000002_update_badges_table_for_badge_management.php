<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            if (!Schema::hasColumn('badges', 'badge_series_id')) {
                $table->unsignedBigInteger('badge_series_id')->nullable()->after('badge_category_id');
            }

            if (!Schema::hasColumn('badges', 'code')) {
                $table->string('code')->nullable()->unique()->after('badge_series_id');
            }

            if (Schema::hasColumn('badges', 'image_url') && !Schema::hasColumn('badges', 'image_path')) {
                $table->renameColumn('image_url', 'image_path');
            }

            if (Schema::hasColumn('badges', 'locked_image_url')) {
                $table->dropColumn('locked_image_url');
            }
        });

        Schema::table('badges', function (Blueprint $table) {
            if (Schema::hasColumn('badges', 'badge_series_id')) {
                $table->index('badge_series_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            if (Schema::hasColumn('badges', 'badge_series_id')) {
                $table->dropIndex(['badge_series_id']);
                $table->dropColumn('badge_series_id');
            }

            if (Schema::hasColumn('badges', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }

            if (Schema::hasColumn('badges', 'image_path') && !Schema::hasColumn('badges', 'image_url')) {
                $table->renameColumn('image_path', 'image_url');
            }

            if (!Schema::hasColumn('badges', 'locked_image_url')) {
                $table->string('locked_image_url')->nullable();
            }
        });
    }
};