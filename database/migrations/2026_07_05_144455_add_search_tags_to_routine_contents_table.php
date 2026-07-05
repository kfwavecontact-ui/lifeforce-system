<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('routine_contents', 'search_tags')) {
                $table->string('search_tags')->nullable()->after('target_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routine_contents', function (Blueprint $table) {
            if (Schema::hasColumn('routine_contents', 'search_tags')) {
                $table->dropColumn('search_tags');
            }
        });
    }
};