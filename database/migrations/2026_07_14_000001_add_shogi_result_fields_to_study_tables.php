<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_study_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('learning_session_id')->nullable()->after('routine_content_id');
            $table->string('component_type', 50)->nullable()->after('learning_session_id');
            $table->uuid('completion_key')->nullable()->unique()->after('component_type');
            $table->timestamp('last_activity_at')->nullable()->after('is_completed');
            $table->jsonb('context')->nullable()->after('last_activity_at');

            $table->index(['student_id', 'component_type'], 'routine_study_sessions_student_component_idx');
            $table->index('learning_session_id', 'routine_study_sessions_learning_session_idx');
        });

        Schema::table('routine_study_results', function (Blueprint $table) {
            $table->string('rank', 10)->nullable()->after('score');
            $table->unsignedSmallInteger('stars')->nullable()->after('rank');
            $table->unsignedInteger('total_elapsed_ms')->default(0)->after('stars');
            $table->unsignedInteger('hint_count')->default(0)->after('total_elapsed_ms');
            $table->boolean('used_answer')->default(false)->after('hint_count');
            $table->jsonb('details')->nullable()->after('used_answer');
        });
    }

    public function down(): void
    {
        Schema::table('routine_study_results', function (Blueprint $table) {
            $table->dropColumn([
                'rank',
                'stars',
                'total_elapsed_ms',
                'hint_count',
                'used_answer',
                'details',
            ]);
        });

        Schema::table('routine_study_sessions', function (Blueprint $table) {
            $table->dropIndex('routine_study_sessions_student_component_idx');
            $table->dropIndex('routine_study_sessions_learning_session_idx');
            $table->dropUnique('routine_study_sessions_completion_key_unique');
            $table->dropColumn([
                'learning_session_id',
                'component_type',
                'completion_key',
                'last_activity_at',
                'context',
            ]);
        });
    }
};
