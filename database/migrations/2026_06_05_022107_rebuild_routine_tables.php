<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dropTables = [
            'routine_study_results',
            'routine_study_sessions',
            'student_routine_daily_statuses',
            'student_routine_items',
            'student_routines',
            'routine_package_items',
            'routine_packages',
            'routine_contents',
            'study_results',
            'study_sessions',
            'daily_routine_statuses',
            'routine_completion_logs',
            'learning_routine_logs',
            'learning_routines',
        ];

        foreach ($dropTables as $table) {
            DB::statement('DROP TABLE IF EXISTS "' . $table . '" CASCADE');
        }

        Schema::create('routine_contents', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 100)->nullable();
            $table->string('category_name')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_type', 100)->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('theme_color', 50)->nullable();
            $table->string('learning_url', 500)->nullable();
            $table->string('material_url', 500)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->foreignId('default_completion_type_id')->nullable()->constrained('routine_completion_types')->nullOnDelete();
            $table->decimal('default_target_value', 10, 2)->nullable();
            $table->integer('default_estimated_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('routine_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_type', 100)->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('theme_color', 50)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('target_grade', 100)->nullable();
            $table->string('target_level', 100)->nullable();
            $table->string('tag', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('routine_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_package_id')->constrained('routine_packages')->cascadeOnDelete();
            $table->foreignId('routine_content_id')->constrained('routine_contents')->restrictOnDelete();
            $table->string('item_name');
            $table->string('target_grade', 100)->nullable();
            $table->string('target_level', 100)->nullable();
            $table->string('tag', 100)->nullable();
            $table->foreignId('completion_type_id')->constrained('routine_completion_types')->restrictOnDelete();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->integer('order_no')->default(0);
            $table->boolean('is_required')->default(true);
            $table->text('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('student_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('routine_package_id')->nullable()->constrained('routine_packages')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_routine_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_routine_id')->constrained('student_routines')->cascadeOnDelete();
            $table->foreignId('routine_package_item_id')->nullable()->constrained('routine_package_items')->nullOnDelete();
            $table->foreignId('routine_content_id')->constrained('routine_contents')->restrictOnDelete();
            $table->string('item_name');
            $table->string('target_grade', 100)->nullable();
            $table->string('target_level', 100)->nullable();
            $table->string('tag', 100)->nullable();
            $table->foreignId('completion_type_id')->constrained('routine_completion_types')->restrictOnDelete();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->integer('order_no')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('student_routine_daily_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_routine_item_id')->constrained('student_routine_items')->cascadeOnDelete();
            $table->date('target_date');
            $table->string('status', 50)->default('not_started');
            $table->integer('achieved_days')->default(0);
            $table->integer('elapsed_days')->default(0);
            $table->decimal('achievement_rate', 5, 2)->default(0);
            $table->timestamp('studied_at')->nullable();
            $table->integer('study_seconds')->nullable();
            $table->timestamp('material_read_at')->nullable();
            $table->timestamp('video_watched_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['student_routine_item_id', 'target_date'], 'srd_status_item_date_unique');
        });

        Schema::create('routine_study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_routine_item_id')->constrained('student_routine_items')->cascadeOnDelete();
            $table->foreignId('routine_content_id')->constrained('routine_contents')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('actual_minutes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });

        Schema::create('routine_study_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_study_session_id')->constrained('routine_study_sessions')->cascadeOnDelete();
            $table->integer('total_questions')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('incorrect_answers')->default(0);
            $table->decimal('accuracy_rate', 5, 2)->default(0);
            $table->integer('score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $dropTables = [
            'routine_study_results',
            'routine_study_sessions',
            'student_routine_daily_statuses',
            'student_routine_items',
            'student_routines',
            'routine_package_items',
            'routine_packages',
            'routine_contents',
        ];

        foreach ($dropTables as $table) {
            DB::statement('DROP TABLE IF EXISTS "' . $table . '" CASCADE');
        }
    }
};