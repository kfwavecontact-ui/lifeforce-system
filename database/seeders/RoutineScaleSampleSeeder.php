<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * ファイル概要: ルーティン一覧の大量件数表示を確認するための開発用サンプルデータSeeder。
 * 役割: ルーティン1,000件、ルーティンアイテム2,000件、構成紐付け5,000件を追加する。
 * 関連画面: 教育 ＞ ルーティン ＞ ルーティン一覧、生徒別の割当可能なルーティン。
 * 関連Service: App\Services\Routine\EducationRoutineService。
 * 利用DBテーブル:
 * - routine_packages: サンプルルーティンを追加。
 * - routine_contents: サンプルルーティンアイテムを追加。
 * - routine_package_items: 各ルーティンへ5アイテムずつ紐付け。
 * - routine_completion_types: 既存の達成条件IDを参照。
 * 利用理由: ページネーション、検索、最近割当、おすすめ、構成アイテム省略表示を大量件数で確認するため。
 * 参照/更新区分: routine_completion_typesは参照、その他3テーブルは追加・サンプル範囲のみ再構築。
 * 注意: 本Seederはローカル開発環境専用。既存の本番データは削除しない。
 */
class RoutineScaleSampleSeeder extends Seeder
{
    private const PACKAGE_COUNT = 1000;
    private const CONTENT_COUNT = 2000;
    private const ITEMS_PER_PACKAGE = 5;
    private const PACKAGE_CODE_PREFIX = 'SAMPLE-RPK-';
    private const CONTENT_CODE_PREFIX = 'SAMPLE-RCT-';
    private const NAME_PREFIX = '【件数確認用】';

    /**
     * 大量表示確認用サンプルデータを作成する。
     */
    public function run(): void
    {
        $this->assertRequiredTables();

        $completionTypeId = DB::table('routine_completion_types')->orderBy('id')->value('id');
        if ($completionTypeId === null) {
            throw new RuntimeException('routine_completion_typesにデータがありません。達成条件マスタを先に登録してください。');
        }

        DB::transaction(function () use ($completionTypeId): void {
            $this->clearPreviousSamples();
            $this->seedContents((int) $completionTypeId);
            $this->seedPackages();
            $this->seedPackageItems((int) $completionTypeId);
        });

        $this->command?->info('ルーティン件数確認用サンプルデータを作成しました。');
        $this->command?->line('・ルーティン: '.self::PACKAGE_COUNT.'件');
        $this->command?->line('・ルーティンアイテム: '.self::CONTENT_COUNT.'件');
        $this->command?->line('・構成紐付け: '.(self::PACKAGE_COUNT * self::ITEMS_PER_PACKAGE).'件');
    }

    /**
     * 必要テーブルの存在を確認する。
     */
    private function assertRequiredTables(): void
    {
        foreach (['routine_packages', 'routine_contents', 'routine_package_items', 'routine_completion_types'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("必要なテーブル {$table} が存在しません。");
            }
        }
    }


    /**
     * 過去に本Seederが作成したサンプルだけを削除する。
     * サンプルルーティンが生徒へ割当済みの場合は、割当データを壊さないため処理を中止する。
     */
    private function clearPreviousSamples(): void
    {
        $packageQuery = DB::table('routine_packages');
        if (Schema::hasColumn('routine_packages', 'package_code')) {
            $packageQuery->where('package_code', 'like', self::PACKAGE_CODE_PREFIX.'%');
        } else {
            $packageQuery->where('name', 'like', self::NAME_PREFIX.'%');
        }

        $packageIds = $packageQuery->pluck('id');

        if ($packageIds->isNotEmpty() && Schema::hasTable('student_routines')) {
            $isAssigned = DB::table('student_routines')->whereIn('routine_package_id', $packageIds)->exists();
            if ($isAssigned) {
                throw new RuntimeException('件数確認用サンプルルーティンが生徒へ割当済みです。割当を取り消してからSeederを再実行してください。');
            }
        }

        if ($packageIds->isNotEmpty()) {
            DB::table('routine_package_items')->whereIn('routine_package_id', $packageIds)->delete();
            DB::table('routine_packages')->whereIn('id', $packageIds)->delete();
        }

        $contentQuery = DB::table('routine_contents');
        if (Schema::hasColumn('routine_contents', 'content_code')) {
            $contentQuery->where('content_code', 'like', self::CONTENT_CODE_PREFIX.'%');
        } else {
            $contentQuery->where('name', 'like', self::NAME_PREFIX.'%');
        }

        $contentIds = $contentQuery->pluck('id');
        if ($contentIds->isNotEmpty()) {
            DB::table('routine_contents')->whereIn('id', $contentIds)->delete();
        }
    }

    /**
     * ルーティンアイテムを2,000件作成する。
     */
    private function seedContents(int $completionTypeId): void
    {
        $rows = [];
        $now = now();
        $categories = [
            ['memory', '記憶力'],
            ['attention', '注意力'],
            ['logic', '論理力'],
            ['judgement', '判断力'],
            ['processing', '処理速度'],
        ];

        for ($index = 1; $index <= self::CONTENT_COUNT; $index++) {
            [$categoryCode, $categoryName] = $categories[($index - 1) % count($categories)];
            $difficulty = (($index - 1) % 5) + 1;
            $minutes = (($index - 1) % 12) + 3;
            $days = (($index - 1) % 25) + 5;

            $row = [
                'content_code' => self::CONTENT_CODE_PREFIX.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'category_code' => $categoryCode,
                'category_name' => $categoryName,
                'name' => self::NAME_PREFIX."{$categoryName}トレーニング ".str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'description' => "大量件数表示を確認するためのサンプルルーティンアイテム {$index} です。",
                'icon_type' => 'sample',
                'theme_color' => '#64748b',
                'learning_page_status' => $index % 7 === 0 ? 'in_progress' : 'completed',
                'difficulty' => $difficulty,
                'estimated_days' => $days,
                'daily_learning_minutes' => $minutes,
                'default_completion_type_id' => $completionTypeId,
                'default_target_value' => (($index - 1) % 10) + 1,
                'default_estimated_minutes' => $minutes,
                'search_tags' => "件数確認,{$categoryName},難易度{$difficulty}",
                'target_grade' => $this->gradeLabel($index),
                'sort_order' => 10000 + $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rows[] = $this->onlyExistingColumns('routine_contents', $row);
        }

        $this->replaceByCodeOrName('routine_contents', 'content_code', self::CONTENT_CODE_PREFIX, $rows);
    }

    /**
     * ルーティンを1,000件作成する。
     */
    private function seedPackages(): void
    {
        $rows = [];
        $now = now();
        $categories = ['記憶力', '注意力', '論理力', '判断力', '処理速度'];

        for ($index = 1; $index <= self::PACKAGE_COUNT; $index++) {
            $category = $categories[($index - 1) % count($categories)];
            $level = (($index - 1) % 5) + 1;

            $row = [
                'package_code' => self::PACKAGE_CODE_PREFIX.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'name' => self::NAME_PREFIX."{$category}ルーティン ".str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'description' => "大量件数表示、検索、ページネーションを確認するためのサンプルルーティン {$index} です。",
                'icon_type' => 'sample',
                'theme_color' => '#475569',
                'category' => $category,
                'target_grade' => $this->gradeLabel($index),
                'target_level' => "Lv{$level}",
                'tag' => '件数確認',
                'search_tags' => "件数確認,{$category},Lv{$level}",
                'sort_order' => 10000 + $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rows[] = $this->onlyExistingColumns('routine_packages', $row);
        }

        $this->replaceByCodeOrName('routine_packages', 'package_code', self::PACKAGE_CODE_PREFIX, $rows);
    }

    /**
     * 各サンプルルーティンへ5件ずつアイテムを紐付ける。
     */
    private function seedPackageItems(int $completionTypeId): void
    {
        $packages = $this->sampleIdMap('routine_packages', 'package_code', self::PACKAGE_CODE_PREFIX);
        $contents = $this->sampleIdMap('routine_contents', 'content_code', self::CONTENT_CODE_PREFIX);

        if (count($packages) !== self::PACKAGE_COUNT || count($contents) !== self::CONTENT_COUNT) {
            throw new RuntimeException('サンプルルーティンまたはアイテムのID取得件数が一致しません。');
        }

        DB::table('routine_package_items')->whereIn('routine_package_id', array_values($packages))->delete();

        $rows = [];
        $now = now();

        for ($packageIndex = 1; $packageIndex <= self::PACKAGE_COUNT; $packageIndex++) {
            $packageCode = self::PACKAGE_CODE_PREFIX.str_pad((string) $packageIndex, 5, '0', STR_PAD_LEFT);
            $packageId = $packages[$packageCode];

            for ($order = 1; $order <= self::ITEMS_PER_PACKAGE; $order++) {
                $contentIndex = (($packageIndex * 7 + $order * 19 - 1) % self::CONTENT_COUNT) + 1;
                $contentCode = self::CONTENT_CODE_PREFIX.str_pad((string) $contentIndex, 5, '0', STR_PAD_LEFT);
                $contentId = $contents[$contentCode];
                $minutes = (($contentIndex - 1) % 12) + 3;

                $row = [
                    'routine_package_id' => $packageId,
                    'routine_content_id' => $contentId,
                    'item_name' => self::NAME_PREFIX.'アイテム '.str_pad((string) $contentIndex, 4, '0', STR_PAD_LEFT),
                    'target_grade' => $this->gradeLabel($packageIndex),
                    'target_level' => 'Lv'.((($packageIndex - 1) % 5) + 1),
                    'tag' => $order <= 3 ? '必須トレーニング' : '追加トレーニング',
                    'completion_type_id' => $completionTypeId,
                    'target_value' => (($contentIndex - 1) % 10) + 1,
                    'estimated_minutes' => $minutes,
                    'required_days' => (($packageIndex - 1) % 25) + 5,
                    'order_no' => $order,
                    'is_required' => $order <= 3,
                    'memo' => "件数確認用構成アイテム {$order}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $rows[] = $this->onlyExistingColumns('routine_package_items', $row);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('routine_package_items')->insert($chunk);
        }
    }

    /**
     * サンプルデータだけを一度削除してから再作成する。
     */
    private function replaceByCodeOrName(string $table, string $codeColumn, string $codePrefix, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * サンプルコードとIDの対応表を取得する。
     */
    private function sampleIdMap(string $table, string $codeColumn, string $prefix): array
    {
        if (Schema::hasColumn($table, $codeColumn)) {
            return DB::table($table)
                ->where($codeColumn, 'like', $prefix.'%')
                ->pluck('id', $codeColumn)
                ->mapWithKeys(fn ($id, $code) => [(string) $code => (int) $id])
                ->all();
        }

        return DB::table($table)
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->values()
            ->mapWithKeys(function ($row, $index) use ($prefix): array {
                $code = $prefix.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT);
                return [$code => (int) $row->id];
            })
            ->all();
    }

    /**
     * 実DBに存在するカラムだけを残す。
     */
    private function onlyExistingColumns(string $table, array $row): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        return array_intersect_key($row, $columns);
    }

    /**
     * 表示確認用の対象学年ラベルを循環生成する。
     */
    private function gradeLabel(int $index): string
    {
        return match (($index - 1) % 6) {
            0 => '年中〜年長',
            1 => '年長〜小1',
            2 => '小1〜小2',
            3 => '小2〜小3',
            4 => '小3〜小4',
            default => '小4〜小6',
        };
    }
}
