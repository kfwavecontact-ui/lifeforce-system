<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('title_categories')) {
            Schema::create('title_categories', function (Blueprint $table) {
                $table->id(); $table->string('name')->unique(); $table->text('description')->nullable();
                $table->integer('display_order')->default(0); $table->boolean('is_active')->default(true); $table->timestamps();
            });
        }
        if (! Schema::hasTable('title_series')) {
            Schema::create('title_series', function (Blueprint $table) {
                $table->id(); $table->foreignId('title_category_id')->nullable()->constrained('title_categories')->nullOnDelete();
                $table->string('name'); $table->text('description')->nullable(); $table->integer('display_order')->default(0);
                $table->boolean('is_active')->default(true); $table->timestamps();
            });
        }
        if (! Schema::hasTable('title_requirement_types')) {
            Schema::create('title_requirement_types', function (Blueprint $table) {
                $table->id(); $table->string('code')->unique(); $table->string('name'); $table->string('unit')->nullable();
                $table->integer('display_order')->default(0); $table->boolean('is_active')->default(true); $table->timestamps();
            });
        }

        Schema::table('titles', function (Blueprint $table) {
            if (! Schema::hasColumn('titles', 'title_category_id')) $table->foreignId('title_category_id')->nullable()->after('id')->constrained('title_categories')->nullOnDelete();
            if (! Schema::hasColumn('titles', 'title_series_id')) $table->foreignId('title_series_id')->nullable()->after('title_category_id')->constrained('title_series')->nullOnDelete();
            if (! Schema::hasColumn('titles', 'code')) $table->string('code')->nullable()->after('title_series_id');
            if (! Schema::hasColumn('titles', 'grant_method')) $table->string('grant_method', 20)->default('both')->after('code');
            if (! Schema::hasColumn('titles', 'level')) $table->unsignedSmallInteger('level')->default(1)->after('name');
            if (! Schema::hasColumn('titles', 'acquisition_message')) $table->text('acquisition_message')->nullable()->after('description');
            if (! Schema::hasColumn('titles', 'allow_regrant')) $table->boolean('allow_regrant')->default(true)->after('acquisition_message');
            if (! Schema::hasColumn('titles', 'notify_on_grant')) $table->boolean('notify_on_grant')->default(true)->after('allow_regrant');
            if (! Schema::hasColumn('titles', 'locked_image_path')) $table->string('locked_image_path')->nullable()->after('image_path');
            if (! Schema::hasColumn('titles', 'is_limited')) $table->boolean('is_limited')->default(false)->after('point_reward');
            if (! Schema::hasColumn('titles', 'start_date')) $table->date('start_date')->nullable()->after('is_limited');
            if (! Schema::hasColumn('titles', 'end_date')) $table->date('end_date')->nullable()->after('start_date');
            if (! Schema::hasColumn('titles', 'created_by')) $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('titles', 'updated_by')) $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('titles', 'condition_operator')) $table->string('condition_operator', 10)->default('and')->after('updated_by');
        });

        DB::table('titles')->orderBy('id')->get()->each(function ($title) {
            $level = match ($title->rarity ?? 'normal') { 'rare' => 3, 'epic' => 5, 'legend' => 8, 'limited' => 10, default => 1 };
            DB::table('titles')->where('id', $title->id)->update(['code' => $title->code ?: 'LF-TTL-' . str_pad((string)$title->id, 3, '0', STR_PAD_LEFT), 'level' => $title->level ?: $level]);
        });
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS titles_code_unique ON titles (code)');

        if (! Schema::hasTable('title_requirements')) {
            Schema::create('title_requirements', function (Blueprint $table) {
                $table->id(); $table->foreignId('title_id')->constrained('titles')->cascadeOnDelete();
                $table->foreignId('title_requirement_type_id')->nullable()->constrained('title_requirement_types')->nullOnDelete();
                $table->string('requirement_type')->nullable(); $table->integer('requirement_value')->default(1); $table->timestamps();
            });
        }

        Schema::table('student_titles', function (Blueprint $table) {
            if (! Schema::hasColumn('student_titles', 'removal_reason_code')) $table->string('removal_reason_code', 40)->nullable()->after('removed_by');
            if (! Schema::hasColumn('student_titles', 'removal_reason_detail')) $table->text('removal_reason_detail')->nullable()->after('removal_reason_code');
            if (! Schema::hasColumn('student_titles', 'regrant_source_id')) $table->foreignId('regrant_source_id')->nullable()->after('removal_reason_detail')->constrained('student_titles')->nullOnDelete();
            if (! Schema::hasColumn('student_titles', 'grant_notification_sent')) $table->boolean('grant_notification_sent')->default(false)->after('regrant_source_id');
            if (! Schema::hasColumn('student_titles', 'removal_notification_sent')) $table->boolean('removal_notification_sent')->default(false)->after('grant_notification_sent');
            if (! Schema::hasColumn('student_titles', 'is_displayed')) $table->boolean('is_displayed')->default(true)->after('removal_notification_sent');
        });
        try { Schema::table('student_titles', fn (Blueprint $table) => $table->dropUnique('student_titles_student_title_unique')); } catch (Throwable $e) {}
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS student_titles_active_unique ON student_titles (student_id, title_id) WHERE status = 'active'");

        Schema::table('title_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('title_histories', 'operated_by')) $table->foreignId('operated_by')->nullable()->after('event_type')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('title_histories', 'reason_code')) $table->string('reason_code', 40)->nullable()->after('operated_by');
            if (! Schema::hasColumn('title_histories', 'reason_detail')) $table->text('reason_detail')->nullable()->after('reason_code');
        });
        DB::table('title_histories')->whereNull('operated_by')->whereNotNull('operator_id')->update(['operated_by' => DB::raw('operator_id')]);
        DB::table('title_histories')->whereNull('reason_detail')->whereNotNull('reason')->update(['reason_detail' => DB::raw('reason')]);

        $categories = ['脳開発','将棋','資格','イベント','継続','挑戦','特別'];
        foreach ($categories as $i => $name) DB::table('title_categories')->updateOrInsert(['name'=>$name], ['display_order'=>$i+1,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        $types = [['learning_count','学習回数','回'],['continuous_days','継続日数','日'],['challenge_clear','チャレンジ達成','回'],['event_participation','イベント参加','回'],['manual_only','手動判定',null]];
        foreach ($types as $i=>$row) DB::table('title_requirement_types')->updateOrInsert(['code'=>$row[0]], ['name'=>$row[1],'unit'=>$row[2],'display_order'=>$i+1,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS student_titles_active_unique');
        DB::statement('DROP INDEX IF EXISTS titles_code_unique');
        Schema::dropIfExists('title_requirements'); Schema::dropIfExists('title_requirement_types');
        // マスタ列は実データ保護のためdownで自動削除しない。
    }
};
