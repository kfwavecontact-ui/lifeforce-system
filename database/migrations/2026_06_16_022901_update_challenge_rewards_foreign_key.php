<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenge_rewards', function (Blueprint $table) {

            $table->dropForeign([
                'challenge_id'
            ]);

        });

        Schema::table('challenge_rewards', function (Blueprint $table) {

            $table->foreign('challenge_id')
                ->references('id')
                ->on('challenges')
                ->cascadeOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('challenge_rewards', function (Blueprint $table) {

            $table->dropForeign([
                'challenge_id'
            ]);

        });

        Schema::table('challenge_rewards', function (Blueprint $table) {

            $table->foreign('challenge_id')
                ->references('id')
                ->on('challenges');

        });
    }
};