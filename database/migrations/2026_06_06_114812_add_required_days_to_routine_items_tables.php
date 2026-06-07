<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_package_items', function (Blueprint $table) {
            $table->integer('required_days')
                ->nullable()
                ->after('target_value')
                ->comment('達成必要日数');
        });

        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->integer('required_days')
                ->nullable()
                ->after('target_value')
                ->comment('達成必要日数');
        });
    }

    public function down(): void
    {
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->dropColumn('required_days');
        });

        Schema::table('routine_package_items', function (Blueprint $table) {
            $table->dropColumn('required_days');
        });
    }
};