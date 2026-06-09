<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {

            $table->string('last_name_kana')
                ->nullable()
                ->after('last_name');

            $table->string('first_name_kana')
                ->nullable()
                ->after('first_name');

            $table->string('profile_image_path')
                ->nullable()
                ->after('remarks');

        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {

            $table->dropColumn([
                'last_name_kana',
                'first_name_kana',
                'profile_image_path',
            ]);

        });
    }
};