<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_routines', function (Blueprint $table) {
            $table->date('completed_at')->nullable()->after('end_date');
            $table->unsignedTinyInteger('self_evaluation_score')->nullable()->after('completed_at');
            $table->text('self_evaluation_comment')->nullable()->after('self_evaluation_score');
        });
    }

    public function down(): void
    {
        Schema::table('student_routines', function (Blueprint $table) {
            $table->dropColumn([
                'completed_at',
                'self_evaluation_score',
                'self_evaluation_comment',
            ]);
        });
    }
};