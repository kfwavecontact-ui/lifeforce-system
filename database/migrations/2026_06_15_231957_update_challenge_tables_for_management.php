<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->string('challenge_type')->default('score')->after('icon_path');
            $table->text('requirement_description')->nullable()->after('challenge_type');
            $table->integer('max_score')->nullable()->after('requirement_description');
        });

        Schema::table('challenge_rewards', function (Blueprint $table) {
            $table->integer('sort_order')->default(1)->after('point_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenge_rewards', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn([
                'challenge_type',
                'requirement_description',
                'max_score',
            ]);
        });
    }
};
