<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_pages')) {
            $missing = [];
            foreach ([
                'version', 'time_limit_seconds', 'attempt_limit', 'is_random', 'allow_resume',
                'bgm_enabled', 'sound_enabled', 'status', 'publication_status',
                'publish_start_at', 'publish_end_at', 'published_at', 'created_by', 'updated_by',
                'created_at', 'updated_at',
            ] as $column) {
                if (! Schema::hasColumn('learning_pages', $column)) {
                    $missing[] = $column;
                }
            }

            if ($missing) {
                Schema::table('learning_pages', function (Blueprint $table) use ($missing) {
                    if (in_array('version', $missing, true)) $table->integer('version')->default(1);
                    if (in_array('time_limit_seconds', $missing, true)) $table->integer('time_limit_seconds')->nullable();
                    if (in_array('attempt_limit', $missing, true)) $table->integer('attempt_limit')->nullable();
                    if (in_array('is_random', $missing, true)) $table->boolean('is_random')->default(false);
                    if (in_array('allow_resume', $missing, true)) $table->boolean('allow_resume')->default(true);
                    if (in_array('bgm_enabled', $missing, true)) $table->boolean('bgm_enabled')->default(false);
                    if (in_array('sound_enabled', $missing, true)) $table->boolean('sound_enabled')->default(true);
                    if (in_array('status', $missing, true)) $table->string('status', 20)->default('draft');
                    if (in_array('publication_status', $missing, true)) $table->string('publication_status', 20)->default('unpublished');
                    if (in_array('publish_start_at', $missing, true)) $table->timestamp('publish_start_at')->nullable();
                    if (in_array('publish_end_at', $missing, true)) $table->timestamp('publish_end_at')->nullable();
                    if (in_array('published_at', $missing, true)) $table->timestamp('published_at')->nullable();
                    if (in_array('created_by', $missing, true)) $table->unsignedBigInteger('created_by')->nullable();
                    if (in_array('updated_by', $missing, true)) $table->unsignedBigInteger('updated_by')->nullable();
                    if (in_array('created_at', $missing, true)) $table->timestamp('created_at')->nullable();
                    if (in_array('updated_at', $missing, true)) $table->timestamp('updated_at')->nullable();
                });
            }
        }

        if (Schema::hasTable('learning_sessions')) {
            $missing = [];
            foreach (['session_no', 'title', 'subtitle', 'show_subtitle', 'developer_note', 'development_status', 'is_published', 'sort_order', 'created_at', 'updated_at'] as $column) {
                if (! Schema::hasColumn('learning_sessions', $column)) $missing[] = $column;
            }
            if ($missing) {
                Schema::table('learning_sessions', function (Blueprint $table) use ($missing) {
                    if (in_array('session_no', $missing, true)) $table->integer('session_no')->default(0);
                    if (in_array('title', $missing, true)) $table->string('title')->default('学習日');
                    if (in_array('subtitle', $missing, true)) $table->string('subtitle')->nullable();
                    if (in_array('show_subtitle', $missing, true)) $table->boolean('show_subtitle')->default(false);
                    if (in_array('developer_note', $missing, true)) $table->text('developer_note')->nullable();
                    if (in_array('development_status', $missing, true)) $table->string('development_status', 20)->default('not_started');
                    if (in_array('is_published', $missing, true)) $table->boolean('is_published')->default(false);
                    if (in_array('sort_order', $missing, true)) $table->integer('sort_order')->default(0);
                    if (in_array('created_at', $missing, true)) $table->timestamp('created_at')->nullable();
                    if (in_array('updated_at', $missing, true)) $table->timestamp('updated_at')->nullable();
                });
            }
        }

        if (Schema::hasTable('learning_steps')) {
            $missing = [];
            foreach (['step_type', 'title', 'sort_order', 'is_required', 'created_at', 'updated_at'] as $column) {
                if (! Schema::hasColumn('learning_steps', $column)) $missing[] = $column;
            }
            if ($missing) {
                Schema::table('learning_steps', function (Blueprint $table) use ($missing) {
                    if (in_array('step_type', $missing, true)) $table->string('step_type', 50)->default('description');
                    if (in_array('title', $missing, true)) $table->string('title')->default('ステップ');
                    if (in_array('sort_order', $missing, true)) $table->integer('sort_order')->default(0);
                    if (in_array('is_required', $missing, true)) $table->boolean('is_required')->default(false);
                    if (in_array('created_at', $missing, true)) $table->timestamp('created_at')->nullable();
                    if (in_array('updated_at', $missing, true)) $table->timestamp('updated_at')->nullable();
                });
            }
        }

        if (Schema::hasTable('learning_step_contents')) {
            $missing = [];
            foreach (['content_title', 'body', 'media_type', 'media_path', 'settings', 'questions', 'created_at', 'updated_at'] as $column) {
                if (! Schema::hasColumn('learning_step_contents', $column)) $missing[] = $column;
            }
            if ($missing) {
                Schema::table('learning_step_contents', function (Blueprint $table) use ($missing) {
                    if (in_array('content_title', $missing, true)) $table->string('content_title')->nullable();
                    if (in_array('body', $missing, true)) $table->text('body')->nullable();
                    if (in_array('media_type', $missing, true)) $table->string('media_type', 50)->nullable();
                    if (in_array('media_path', $missing, true)) $table->string('media_path', 2048)->nullable();
                    if (in_array('settings', $missing, true)) $table->json('settings')->nullable();
                    if (in_array('questions', $missing, true)) $table->json('questions')->nullable();
                    if (in_array('created_at', $missing, true)) $table->timestamp('created_at')->nullable();
                    if (in_array('updated_at', $missing, true)) $table->timestamp('updated_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // 既存環境の欠損列を補修するmigrationのため、ロールバック時も列は削除しません。
    }
};
