<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_titles', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('is_equipped');
            $table->string('grant_method', 30)->default('manual')->after('status');
            $table->unsignedBigInteger('granted_by')->nullable()->after('grant_method');
            $table->text('grant_reason')->nullable()->after('granted_by');
            $table->timestamp('removed_at')->nullable()->after('grant_reason');
            $table->unsignedBigInteger('removed_by')->nullable()->after('removed_at');
            $table->text('removal_reason')->nullable()->after('removed_by');
            $table->unique(['student_id', 'title_id'], 'student_titles_student_title_unique');
            $table->index(['status', 'acquired_at'], 'student_titles_status_acquired_index');
            $table->index(['student_id', 'is_equipped'], 'student_titles_student_equipped_index');
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('removed_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });

        // 旧Migrationで文字化けした初期タグは、未使用の場合だけ安全に正規化する。
        if (Schema::hasTable('title_tags') && Schema::hasTable('title_tag_relations')
            && DB::table('title_tag_relations')->count() === 0) {
            $tagNames = ['脳開発', '将棋', '資格', 'イベント', '継続', '努力', '初心者', '中級者', '上級者', '限定', '全国', '特別'];
            foreach ($tagNames as $index => $name) {
                DB::table('title_tags')->updateOrInsert(
                    ['display_order' => $index + 1],
                    ['name' => $name, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        Schema::create('title_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_title_id')->nullable();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('title_id');
            $table->string('event_type', 30);
            $table->timestamp('event_at');
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_equipped')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('student_title_id')->references('id')->on('student_titles')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('title_id')->references('id')->on('titles')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['event_type', 'event_at'], 'title_histories_event_date_index');
            $table->index(['student_id', 'event_at'], 'title_histories_student_date_index');
            $table->index(['title_id', 'event_at'], 'title_histories_title_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_histories');
        Schema::table('student_titles', function (Blueprint $table) {
            $table->dropForeign(['granted_by']);
            $table->dropForeign(['removed_by']);
            $table->dropUnique('student_titles_student_title_unique');
            $table->dropIndex('student_titles_status_acquired_index');
            $table->dropIndex('student_titles_student_equipped_index');
            $table->dropColumn(['status','grant_method','granted_by','grant_reason','removed_at','removed_by','removal_reason']);
        });
    }
};
