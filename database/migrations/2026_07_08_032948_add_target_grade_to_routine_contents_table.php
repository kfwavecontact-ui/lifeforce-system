<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTargetGradeToRoutineContentsTable extends Migration
{
    public function up(): void
    {
        Schema::table('routine_contents', function (Blueprint $table) {
            $table->string('target_grade')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('routine_contents', function (Blueprint $table) {
            $table->dropColumn('target_grade');
        });
    }
}