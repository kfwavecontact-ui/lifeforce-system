<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 旧Seederが保存したEnum非対応値を正式値へ統一する。
        DB::table('student_titles')->where('grant_method', 'sample')->update(['grant_method' => 'manual']);
        DB::table('student_titles')->whereNull('status')->update(['status' => 'active']);
        DB::table('student_titles')->where('is_equipped', true)->update(['is_equipped' => false]);
        DB::table('title_histories')->where('is_equipped', true)->update(['is_equipped' => false]);

        // 現行画面では装備履歴を扱わない。旧イベントは監査情報をmetadataへ残し、一覧対象外へ退避する。
        DB::table('title_histories')->whereIn('event_type', ['equipped', 'unequipped'])->orderBy('id')->get()->each(function ($row): void {
            $metadata = json_decode($row->metadata ?? '[]', true) ?: [];
            $metadata['legacy_event_type'] = $row->event_type;
            DB::table('title_histories')->where('id', $row->id)->update([
                'event_type' => 'granted',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        });

        if (! Schema::hasColumn('title_tag_relations', 'id')) {
            Schema::table('title_tag_relations', fn (Blueprint $table) => $table->id()->first());
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS title_tag_relations_unique ON title_tag_relations (title_id, title_tag_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS titles_category_series_index ON titles (title_category_id, title_series_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS titles_active_order_index ON titles (is_active, display_order)');
        DB::statement('CREATE INDEX IF NOT EXISTS title_histories_type_date_index ON title_histories (event_type, event_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS title_tag_relations_unique');
        DB::statement('DROP INDEX IF EXISTS titles_category_series_index');
        DB::statement('DROP INDEX IF EXISTS titles_active_order_index');
        DB::statement('DROP INDEX IF EXISTS title_histories_type_date_index');
    }
};
