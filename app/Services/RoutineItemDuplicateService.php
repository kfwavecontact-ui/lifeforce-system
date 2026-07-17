<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ルーティンアイテム複製Service。
 *
 * 役割:
 * - routine_contentsを複製する。
 * - 複製元に紐づく学習ページ階層を、新しい外部キーへ付け替えて複製する。
 * - 複製対象の画像を、新しい学習ページ／学習日／ステップのStorage階層へ物理コピーする。
 * - 失敗時はDBトランザクションをロールバックし、作成済み画像も補償削除する。
 *
 * 関連画面:
 * - システム > ルーティン管理 > ルーティンアイテム一覧 > 複製
 *
 * 参照テーブル:
 * - routine_contents: 複製元アイテム本体を取得する。
 * - routine_package_items: 一覧上で使用される表示名を取得する。
 * - learning_pages: アイテムに紐づく学習ページを取得する。
 * - learning_sessions: 学習ページ配下の学習日を取得する。
 * - learning_steps: 学習日配下のステップを取得する。
 * - learning_step_contents: ステップ配下の本文・設定・画像パスを取得する。
 *
 * 更新テーブル:
 * - routine_contents
 * - learning_pages
 * - learning_sessions
 * - learning_steps
 * - learning_step_contents
 *
 * 設計上の注意:
 * - 元データは階層ごとではなく、各テーブル1回のSELECTで一括取得する。
 * - 親レコードの新IDが必要なためINSERTは順序を維持し、旧IDと新IDの対応表を保持する。
 * - Storageコピーを含むため、例外時はDBロールバックだけでなく画像の補償削除も行う。
 */
class RoutineItemDuplicateService
{
    /** @var array<string, array<int, string>> テーブルごとのカラム一覧キャッシュ。 */
    private array $tableColumns = [];

    /** @var array<string, int|float|string|bool> 複製処理の工程別計測値。 */
    private array $profile = [];

    public function __construct(
        private readonly LearningContentMediaPathService $mediaPathService,
        private readonly LearningMediaService $mediaService,
    ) {
    }

    /**
     * ルーティンアイテムと学習ページ階層を複製する。
     *
     * @param int $sourceRoutineContentId 複製元routine_contents.id。
     * @return int 新しく作成したroutine_contents.id。
     *
     * @throws Throwable DBまたはStorage処理に失敗した場合。
     */
    public function duplicate(int $sourceRoutineContentId): int
    {
        /** @var array<string, string> $copiedMediaPaths 複製先Storageキーを重複なしで保持する。 */
        $copiedMediaPaths = [];
        $totalStartedAt = hrtime(true);
        $newRoutineContentId = null;

        // 計測値と実行時キャッシュは、複製処理ごとに必ず初期化する。
        $this->resetProfile($sourceRoutineContentId);
        $this->mediaPathService->clearDuplicateCache();
        $this->mediaPathService->resetProfile();
        $this->mediaService->clearRuntimeCache();
        $this->mediaService->resetProfile();

        try {
            $newRoutineContentId = DB::transaction(function () use ($sourceRoutineContentId, &$copiedMediaPaths): int {
                // -----------------------------------------------------------------
                // Step 1: 複製元アイテムを取得し、新しいroutine_contentsを作成する。
                // -----------------------------------------------------------------
                $startedAt = hrtime(true);
                $sourceRoutineContent = DB::table('routine_contents')
                    ->where('id', $sourceRoutineContentId)
                    ->first();
                $this->profile['routine_source_select_ms'] = $this->elapsedMilliseconds($startedAt);

                abort_if(empty($sourceRoutineContent), 404);

                $startedAt = hrtime(true);
                $routinePayload = $this->makeDuplicatePayload('routine_contents', $sourceRoutineContent);
                $routinePayload['name'] = $this->duplicateDisplayName($sourceRoutineContentId, $sourceRoutineContent);

                if (array_key_exists('content_code', $routinePayload)) {
                    $routinePayload['content_code'] = $this->nextContentCode();
                }

                $newId = DB::table('routine_contents')->insertGetId($routinePayload);
                $this->profile['routine_insert_ms'] = $this->elapsedMilliseconds($startedAt);

                // -----------------------------------------------------------------
                // Step 2: 学習ページ階層を一括取得し、親子関係を維持して複製する。
                // -----------------------------------------------------------------
                $this->duplicateLearningTree(
                    $sourceRoutineContentId,
                    $newId,
                    $copiedMediaPaths,
                );

                return $newId;
            });

            $this->profile['success'] = true;

            return $newRoutineContentId;
        } catch (Throwable $exception) {
            // DBはtransactionで戻るが、Storageは自動では戻らないため補償削除する。
            $cleanupStartedAt = hrtime(true);
            $this->deleteCopiedMedia($copiedMediaPaths);
            $this->profile['compensation_delete_ms'] = $this->elapsedMilliseconds($cleanupStartedAt);
            $this->profile['success'] = false;
            $this->profile['exception_class'] = $exception::class;
            $this->profile['exception_message'] = $exception->getMessage();

            throw $exception;
        } finally {
            $this->profile['new_routine_content_id'] = $newRoutineContentId ?? 0;
            $this->profile['copied_media_files'] = count($copiedMediaPaths);
            $this->profile['total_ms'] = $this->elapsedMilliseconds($totalStartedAt);
            $this->writeProfileLog();

            // 長寿命Worker等でも次の処理へキャッシュ状態を持ち越さない。
            $this->mediaPathService->clearDuplicateCache();
            $this->mediaService->clearRuntimeCache();
        }
    }

    /**
     * 複製元の学習ページ階層を一括取得して複製する。
     *
     * SELECT回数をページ数・学習日数に比例させないため、各テーブルを1回ずつ取得し、
     * Collection::groupBy()で親ID単位に整理してからINSERTする。
     *
     * @param array<int|string, string> $copiedMediaPaths 補償削除対象となる新規Storageキー。
     */
    private function duplicateLearningTree(
        int $sourceRoutineContentId,
        int $newRoutineContentId,
        array &$copiedMediaPaths,
    ): void {
        if (! Schema::hasTable('learning_pages')) {
            return;
        }

        $routineForeignKey = Schema::hasColumn('learning_pages', 'routine_content_id')
            ? 'routine_content_id'
            : 'routine_item_id';

        $startedAt = hrtime(true);
        $pages = DB::table('learning_pages')
            ->where($routineForeignKey, $sourceRoutineContentId)
            ->orderBy('id')
            ->get();
        $this->profile['learning_pages_select_ms'] = $this->elapsedMilliseconds($startedAt);
        $this->profile['learning_pages_count'] = $pages->count();

        if ($pages->isEmpty()) {
            return;
        }

        $pageIds = $pages->pluck('id')->map(fn ($id) => (int) $id)->all();

        $startedAt = hrtime(true);
        $sessions = $this->loadSessions($pageIds);
        $this->profile['learning_sessions_select_ms'] = $this->elapsedMilliseconds($startedAt);
        $this->profile['learning_sessions_count'] = $sessions->count();

        $sessionIds = $sessions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $startedAt = hrtime(true);
        $steps = $this->loadSteps($sessionIds);
        $this->profile['learning_steps_select_ms'] = $this->elapsedMilliseconds($startedAt);
        $this->profile['learning_steps_count'] = $steps->count();

        $stepIds = $steps->pluck('id')->map(fn ($id) => (int) $id)->all();
        $startedAt = hrtime(true);
        $contents = $this->loadStepContents($stepIds);
        $this->profile['learning_step_contents_select_ms'] = $this->elapsedMilliseconds($startedAt);
        $this->profile['learning_step_contents_count'] = $contents->count();

        $sessionsByPage = $sessions->groupBy('learning_page_id');
        $stepsBySession = $steps->groupBy('learning_session_id');
        $contentsByStep = $contents->groupBy('learning_step_id');

        $pageInsertMs = 0.0;
        $sessionInsertMs = 0.0;
        $stepInsertMs = 0.0;
        $contentInsertMs = 0.0;
        $contentMediaMs = 0.0;

        foreach ($pages as $sourcePage) {
            $pagePayload = $this->makeDuplicatePayload('learning_pages', $sourcePage);
            $pagePayload[$routineForeignKey] = $newRoutineContentId;

            if (array_key_exists('title', $pagePayload) && empty($pagePayload['title'])) {
                $pagePayload['title'] = '学習ページ';
            }

            $startedAt = hrtime(true);
            $newPageId = DB::table('learning_pages')->insertGetId($pagePayload);
            $pageInsertMs += $this->elapsedMilliseconds($startedAt);

            foreach ($sessionsByPage->get($sourcePage->id, collect()) as $sourceSession) {
                $sessionPayload = $this->makeDuplicatePayload('learning_sessions', $sourceSession);
                $sessionPayload['learning_page_id'] = $newPageId;
                $startedAt = hrtime(true);
                $newSessionId = DB::table('learning_sessions')->insertGetId($sessionPayload);
                $sessionInsertMs += $this->elapsedMilliseconds($startedAt);

                foreach ($stepsBySession->get($sourceSession->id, collect()) as $sourceStep) {
                    $stepPayload = $this->makeDuplicatePayload('learning_steps', $sourceStep);
                    $stepPayload['learning_session_id'] = $newSessionId;
                    $startedAt = hrtime(true);
                    $newStepId = DB::table('learning_steps')->insertGetId($stepPayload);
                    $stepInsertMs += $this->elapsedMilliseconds($startedAt);

                    foreach ($contentsByStep->get($sourceStep->id, collect()) as $sourceContent) {
                        $contentPayload = $this->makeDuplicatePayload('learning_step_contents', $sourceContent);
                        $contentPayload['learning_step_id'] = $newStepId;

                        $startedAt = hrtime(true);
                        $contentPayload = $this->mediaPathService->duplicateContentMedia(
                            $contentPayload,
                            $newPageId,
                            $newSessionId,
                            $newStepId,
                            $copiedMediaPaths,
                        );
                        $contentMediaMs += $this->elapsedMilliseconds($startedAt);

                        $startedAt = hrtime(true);
                        DB::table('learning_step_contents')->insert($contentPayload);
                        $contentInsertMs += $this->elapsedMilliseconds($startedAt);
                    }
                }
            }
        }

        $this->profile['learning_pages_insert_ms'] = round($pageInsertMs, 3);
        $this->profile['learning_sessions_insert_ms'] = round($sessionInsertMs, 3);
        $this->profile['learning_steps_insert_ms'] = round($stepInsertMs, 3);
        $this->profile['learning_step_contents_insert_ms'] = round($contentInsertMs, 3);
        $this->profile['content_media_processing_ms'] = round($contentMediaMs, 3);
    }

    /** @return Collection<int, object> */
    private function loadSessions(array $pageIds): Collection
    {
        if ($pageIds === [] || ! Schema::hasTable('learning_sessions')) {
            return collect();
        }

        return DB::table('learning_sessions')
            ->whereIn('learning_page_id', $pageIds)
            ->orderBy('learning_page_id')
            ->orderBy('session_no')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, object> */
    private function loadSteps(array $sessionIds): Collection
    {
        if ($sessionIds === [] || ! Schema::hasTable('learning_steps')) {
            return collect();
        }

        return DB::table('learning_steps')
            ->whereIn('learning_session_id', $sessionIds)
            ->orderBy('learning_session_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, object> */
    private function loadStepContents(array $stepIds): Collection
    {
        if ($stepIds === [] || ! Schema::hasTable('learning_step_contents')) {
            return collect();
        }

        return DB::table('learning_step_contents')
            ->whereIn('learning_step_id', $stepIds)
            ->orderBy('learning_step_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * 元レコードから主キーを除外し、現在のDBに存在するカラムだけを複製する。
     *
     * migration適用差がある開発環境でもSQLエラーを起こさないため、
     * Schema::getColumnListing()の結果をテーブル単位でキャッシュして利用する。
     */
    private function makeDuplicatePayload(string $table, object $source): array
    {
        $payload = collect((array) $source)
            ->reject(fn ($value, $key) => trim((string) $key) === 'id')
            ->all();

        $columns = $this->tableColumns[$table] ??= Schema::getColumnListing($table);
        $payload = array_intersect_key($payload, array_flip($columns));
        $userId = Auth::id() ?? 1;

        foreach (['created_at', 'updated_at'] as $timestampColumn) {
            if (array_key_exists($timestampColumn, $payload)) {
                $payload[$timestampColumn] = now();
            }
        }

        foreach (['created_by', 'updated_by'] as $userColumn) {
            if (array_key_exists($userColumn, $payload)) {
                $payload[$userColumn] = $userId;
            }
        }

        return $payload;
    }

    /**
     * 一覧で表示されるitem_nameを優先して複製名を作成する。
     */
    private function duplicateDisplayName(int $sourceRoutineContentId, object $source): string
    {
        $packageItemName = DB::table('routine_package_items')
            ->where('routine_content_id', $sourceRoutineContentId)
            ->whereNotNull('item_name')
            ->where('item_name', '<>', '')
            ->orderBy('order_no')
            ->value('item_name');

        $baseName = trim((string) ($packageItemName ?: $source->name ?: 'ルーティンアイテム'));

        return $baseName . '（複製）';
    }

    /**
     * routine_contents.content_code用の未使用コードを採番する。
     */
    private function nextContentCode(): string
    {
        // 既存Controllerの採番規則を維持し、最大IDの次番号を4桁で付与する。
        $nextId = (int) DB::table('routine_contents')->max('id') + 1;

        return 'CONT-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }

    /** 複製処理の計測値を初期化する。 */
    private function resetProfile(int $sourceRoutineContentId): void
    {
        $this->profile = [
            'profile_name' => 'routine_item_duplicate',
            'source_routine_content_id' => $sourceRoutineContentId,
            'success' => false,
        ];
    }

    /**
     * 複製工程・JSON解析・Storage通信の計測結果をLaravelログへ出力する。
     *
     * ログは性能確認専用であり、複製結果やトランザクション制御には影響しない。
     */
    private function writeProfileLog(): void
    {
        $profile = array_merge(
            $this->profile,
            ['media_path' => $this->mediaPathService->profile()],
            ['storage' => $this->mediaService->profile()],
        );

        Log::info('Routine item duplicate profile', $profile);
    }

    /** hrtime()の差分をミリ秒へ変換する。 */
    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    /**
     * 例外発生前に作成済みの画像を削除する。
     * 後片付け失敗で元例外を隠さないよう、削除例外は握りつぶす。
     *
     * @param array<int|string, string> $paths
     */
    private function deleteCopiedMedia(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            try {
                $this->mediaService->delete($path);
            } catch (Throwable) {
                // 元のDB／Storage例外を優先する。
            }
        }
    }
}
