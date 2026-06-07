<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_routines', function (Blueprint $table) {
            $table->text('teacher_comment')->nullable()->after('self_evaluation_comment');
            $table->unsignedBigInteger('teacher_comment_user_id')->nullable()->after('teacher_comment');
            $table->timestamp('teacher_comment_at')->nullable()->after('teacher_comment_user_id');
            $table->string('completion_reason')->nullable()->after('teacher_comment_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_routines', function (Blueprint $table) {
            $table->dropColumn([
                'teacher_comment',
                'teacher_comment_user_id',
                'teacher_comment_at',
                'completion_reason',
            ]);
        });
    }
};