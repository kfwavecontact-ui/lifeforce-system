<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('profile_image_path', 'image_path');
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->renameColumn('image_url', 'image_path');
            $table->renameColumn('locked_image_url', 'locked_image_path');
        });

        Schema::table('titles', function (Blueprint $table) {
            $table->renameColumn('image_url', 'image_path');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('image_path', 'profile_image_path');
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->renameColumn('image_path', 'image_url');
            $table->renameColumn('locked_image_path', 'locked_image_url');
        });

        Schema::table('titles', function (Blueprint $table) {
            $table->renameColumn('image_path', 'image_url');
        });
    }
};