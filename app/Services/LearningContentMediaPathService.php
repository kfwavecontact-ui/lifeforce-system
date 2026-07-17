<?php

namespace App\Services;


/**
 * learning_step_contents内の画像パス複製Service。
 *
 * 役割:
 * - media_pathとsettings JSONに保存された画像パスを抽出する。
 * - 複製先の学習ページ階層へ画像を物理コピーする。
 * - コピー後のStorageキーへJSON内パスを書き換える。
 *
 * 利用カラム:
 * - learning_step_contents.media_type: media_pathの保存目的を判定する。
 * - learning_step_contents.media_path: ステップ本体の画像／PDF。
 * - learning_step_contents.settings: 背景、問題、回答、解説、コンポーネント画像。
 */
class LearningContentMediaPathService
{
    /** @var array<string, string|null> 同一複製処理内の元画像と複製先画像の対応表。 */
    private array $copyResultCache = [];

    /** @var array<string, int|float> JSON解析・画像参照数の計測値。 */
    private array $profile = [
        'content_records' => 0,
        'media_references' => 0,
        'copy_cache_hits' => 0,
        'json_decode_ms' => 0.0,
        'media_rewrite_ms' => 0.0,
    ];

    public function __construct(private readonly LearningMediaService $mediaService)
    {
    }

    /**
     * 複製処理単位の画像コピー結果キャッシュを初期化する。
     *
     * 同じHTTPリクエスト内で複数の複製処理が実行されても、別の複製先IDへ
     * 古い画像パスを誤って再利用しないよう、処理境界で明示的に破棄する。
     */
    public function clearDuplicateCache(): void
    {
        $this->copyResultCache = [];
    }

    /** 複製性能計測値を初期化する。 */
    public function resetProfile(): void
    {
        $this->profile = [
            'content_records' => 0,
            'media_references' => 0,
            'copy_cache_hits' => 0,
            'json_decode_ms' => 0.0,
            'media_rewrite_ms' => 0.0,
        ];
    }

    /** @return array<string, int|float> */
    public function profile(): array
    {
        return $this->profile;
    }

    /**
     * コンテンツPayload内の全画像を複製し、パスを書き換える。
     *
     * @param array<string, mixed> $contentPayload learning_step_contentsへのINSERT値。
     * @param array<int|string, string> $copiedMediaPaths 失敗時の補償削除対象。
     * @return array<string, mixed>
     */
    public function duplicateContentMedia(
        array $contentPayload,
        int $newLearningPageId,
        int $newLearningSessionId,
        int $newLearningStepId,
        array &$copiedMediaPaths,
    ): array {
        $startedAt = hrtime(true);
        $this->profile['content_records']++;

        $copy = function (?string $sourcePath, string $purpose = 'media') use (
            $newLearningPageId,
            $newLearningSessionId,
            $newLearningStepId,
            &$copiedMediaPaths,
        ): ?string {
            $this->profile['media_references']++;
            $cacheKey = $this->makeCopyCacheKey(
                $sourcePath,
                $newLearningPageId,
                $newLearningSessionId,
                $newLearningStepId,
                $purpose,
            );

            if (array_key_exists($cacheKey, $this->copyResultCache)) {
                $this->profile['copy_cache_hits']++;

                return $this->copyResultCache[$cacheKey];
            }

            $newPath = $this->mediaService->copyIfExists(
                $sourcePath,
                $newLearningPageId,
                $newLearningSessionId,
                $newLearningStepId,
                $purpose,
            );

            $this->copyResultCache[$cacheKey] = $newPath;

            if ($newPath !== null) {
                // 連想配列で保持し、補償削除対象への重複登録も防止する。
                $copiedMediaPaths[$newPath] = $newPath;
            }

            return $newPath;
        };

        // learning_step_contents.media_path: ステップ直下の画像またはPDF。
        if (! empty($contentPayload['media_path'])) {
            $purpose = ($contentPayload['media_type'] ?? null) === 'pdf' ? 'pdf' : 'media';
            $contentPayload['media_path'] = $copy((string) $contentPayload['media_path'], $purpose);
        }

        $jsonStartedAt = hrtime(true);
        $settings = $this->decodeJsonArray($contentPayload['settings'] ?? null);
        $this->profile['json_decode_ms'] += $this->elapsedMilliseconds($jsonStartedAt);

        // 学習画面背景。
        $this->copySettingPath($settings, 'screen_background.image', 'background', $copy);

        // 問題・回答・解説の専用画像。
        foreach ([
            'specific.prompt_image_path' => 'description',
            'specific.answer_image_path' => 'answer',
            'specific.explanation_image_path' => 'commentary',
        ] as $path => $purpose) {
            $this->copySettingPath($settings, $path, $purpose, $copy);
        }

        // コンポーネント画像とX択問題セット内画像。
        $components = data_get($settings, 'components', []);
        if (is_array($components)) {
            foreach ($components as $componentIndex => $component) {
                if (! is_array($component)) {
                    continue;
                }

                $componentType = $component['type'] ?? null;
                if (in_array($componentType, ['image', 'timed_display'], true) && ! empty($component['path'])) {
                    $basePath = "components.{$componentIndex}";
                    data_set($settings, "{$basePath}.path", $copy((string) $component['path'], 'media'));
                    data_set($settings, "{$basePath}.upload_slot", null);
                }

                if ($componentType !== 'choice_question_set') {
                    continue;
                }

                foreach (($component['choice_questions'] ?? []) as $questionIndex => $question) {
                    if (! is_array($question)) {
                        continue;
                    }

                    $questionPath = "components.{$componentIndex}.choice_questions.{$questionIndex}";
                    if (! empty($question['path'])) {
                        data_set($settings, "{$questionPath}.path", $copy((string) $question['path'], 'media'));
                        data_set($settings, "{$questionPath}.upload_slot", null);
                    }

                    foreach (($question['options'] ?? []) as $optionIndex => $option) {
                        if (! is_array($option) || empty($option['path'])) {
                            continue;
                        }

                        $optionPath = "{$questionPath}.options.{$optionIndex}";
                        data_set($settings, "{$optionPath}.path", $copy((string) $option['path'], 'media'));
                        data_set($settings, "{$optionPath}.upload_slot", null);
                    }
                }
            }
        }

        if (array_key_exists('settings', $contentPayload)) {
            $contentPayload['settings'] = json_encode(
                $settings,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        }

        $this->profile['media_rewrite_ms'] += $this->elapsedMilliseconds($startedAt);

        return $contentPayload;
    }

    /**
     * 画像コピー結果を安全に再利用するためのキャッシュキーを生成する。
     *
     * 複製先ステップと用途まで含めることで、同一ステップ内の重複参照だけを
     * まとめ、別ステップの画像所有関係は従来どおり分離する。
     */
    /** hrtime()の差分をミリ秒へ変換する。 */
    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    private function makeCopyCacheKey(
        ?string $sourcePath,
        int $learningPageId,
        int $learningSessionId,
        int $learningStepId,
        string $purpose,
    ): string {
        $normalizedSource = $this->mediaService->normalizeKey($sourcePath) ?? trim((string) $sourcePath);

        return implode('|', [
            $normalizedSource,
            (string) $learningPageId,
            (string) $learningSessionId,
            (string) $learningStepId,
            $purpose,
        ]);
    }

    /**
     * settings内の指定パスをコピー結果へ置換する。
     */
    private function copySettingPath(array &$settings, string $path, string $purpose, callable $copy): void
    {
        $sourcePath = data_get($settings, $path);
        if ($sourcePath) {
            data_set($settings, $path, $copy((string) $sourcePath, $purpose));
        }
    }

    /** @return array<string, mixed> */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
