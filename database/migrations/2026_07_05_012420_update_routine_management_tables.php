<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_packages', function (Blueprint $table) {
            $table->string('package_code')->nullable()->unique()->after('id');
            $table->string('search_tags')->nullable()->after('tag');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('routine_contents', function (Blueprint $table) {
            $table->string('content_code')->nullable()->unique()->after('id');
            $table->string('content_type')->default('common')->after('description');
            $table->foreignId('student_id')->nullable()->after('content_type')->constrained('students')->nullOnDelete();
            $table->string('learning_page_status')->default('not_created')->after('video_url');
            $table->unsignedTinyInteger('difficulty')->nullable()->after('learning_page_status');
            $table->unsignedInteger('estimated_days')->nullable()->after('difficulty');
            $table->unsignedInteger('daily_learning_minutes')->nullable()->after('estimated_days');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('learning_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_content_id')->unique()->constrained('routine_contents')->cascadeOnDelete();
            $table->string('page_type')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('learning_url', 500)->nullable();
            $table->string('status')->default('not_created');
            $table->string('thumbnail_url', 500)->nullable();
            $table->unsignedInteger('time_limit_seconds')->nullable();
            $table->unsignedInteger('question_count')->nullable();
            $table->unsignedInteger('passing_score')->nullable();
            $table->boolean('allow_retry')->default(true);
            $table->boolean('is_shuffle')->default(false);
            $table->boolean('show_answer')->default(true);
            $table->unsignedInteger('estimated_seconds')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_routine_item_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('routine_content_id')->constrained('routine_contents')->cascadeOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_frequently_used')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'routine_content_id'], 'user_routine_item_marks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_routine_item_marks');
        Schema::dropIfExists('learning_pages');

        Schema::table('routine_contents', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'content_code',
                'content_type',
                'student_id',
                'learning_page_status',
                'difficulty',
                'estimated_days',
                'daily_learning_minutes',
                'created_by',
                'updated_by',
            ]);
        });

        Schema::table('routine_packages', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'package_code',
                'search_tags',
                'updated_by',
            ]);
        });
    }
};