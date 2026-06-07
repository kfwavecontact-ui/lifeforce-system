<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('is_active');
            $table->unsignedTinyInteger('self_evaluation_score')->nullable()->after('completed_at');
            $table->text('self_evaluation_comment')->nullable()->after('self_evaluation_score');
            $table->text('teacher_comment')->nullable()->after('self_evaluation_comment');
            $table->foreignId('teacher_comment_user_id')->nullable()->after('teacher_comment')->constrained('users')->nullOnDelete();
            $table->timestamp('teacher_comment_at')->nullable()->after('teacher_comment_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_routine_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_comment_user_id');

            $table->dropColumn([
                'completed_at',
                'self_evaluation_score',
                'self_evaluation_comment',
                'teacher_comment',
                'teacher_comment_at',
            ]);
        });
    }
};