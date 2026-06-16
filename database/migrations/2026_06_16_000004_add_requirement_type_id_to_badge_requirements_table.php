<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_requirements', function (Blueprint $table) {
            if (!Schema::hasColumn('badge_requirements', 'badge_requirement_type_id')) {
                $table->unsignedBigInteger('badge_requirement_type_id')->nullable()->after('badge_id');
                $table->index('badge_requirement_type_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('badge_requirements', function (Blueprint $table) {
            if (Schema::hasColumn('badge_requirements', 'badge_requirement_type_id')) {
                $table->dropIndex(['badge_requirement_type_id']);
                $table->dropColumn('badge_requirement_type_id');
            }
        });
    }
};