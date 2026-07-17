<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 学習ページで使用するファイルのStorage操作Service。
 *
 * 役割:
 * - URLまたはStorageキーを正規化する。
 * - アップロード、複製、削除、プレビューURL生成を一元管理する。
 * - Controllerや他ServiceからS3固有の処理を排除する。
 *
 * Storageディスク:
 * - filesystems.learning_media_diskが設定されていれば、そのディスクを使用する。
 * - 未設定時は既存互換性を維持するためs3を使用する。
 */
class LearningMediaService
{
    /** @var array<string, bool> 正規化済みStorageキーごとの存在確認結果キャッシュ。 */
    private array $existenceCache = [];

    /** @var array<string, int|float> 複製性能計測用の実行時メトリクス。 */
    private array $profile = [
        'exists_requests' => 0,
        'exists_cache_hits' => 0,
        'exists_storage_calls' => 0,
        'exists_ms' => 0.0,
        'copy_requests' => 0,
        'copy_storage_calls' => 0,
        'copy_ms' => 0.0,
        'delete_requests' => 0,
    ];


    public function normalizeKey(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            return ltrim($path, '/');
        }

        $urlPath = rawurldecode((string) parse_url($path, PHP_URL_PATH));
        $key = ltrim($urlPath, '/');
        $bucket = trim((string) config('filesystems.disks.' . $this->disk() . '.bucket'));

        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) {
            $key = substr($key, strlen($bucket) + 1);
        }

        $marker = 'learning-pages/';
        $position = strpos($key, $marker);
        if ($position !== false) {
            $key = substr($key, $position);
        }

        return $key !== '' ? $key : null;
    }

    public function previewUrl(?string $path): string
    {
        $key = $this->normalizeKey($path);
        if (! $key) {
            return '';
        }

        try {
            return Storage::disk($this->disk())->temporaryUrl($key, now()->addHours(6));
        } catch (\Throwable) {
            return Storage::disk($this->disk())->url($key);
        }
    }

    public function upload(
        UploadedFile $file,
        int $learningPageId,
        int $learningSessionId,
        int $learningStepId,
        string $purpose
    ): string {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = $this->directory($learningPageId, $learningSessionId, $learningStepId, $purpose);
        $fileName = Str::uuid()->toString() . '.' . $extension;
        $storedKey = Storage::disk($this->disk())->putFileAs($directory, $file, $fileName);

        if (! $storedKey || ! Storage::disk($this->disk())->exists($storedKey)) {
            throw new RuntimeException('S3へのファイル保存に失敗しました。AWS接続設定とS3書き込み権限を確認してください。');
        }

        return $storedKey;
    }

    public function copy(
        string $sourcePath,
        int $learningPageId,
        int $learningSessionId,
        int $learningStepId,
        string $purpose
    ): string {
        $sourceKey = $this->normalizeKey($sourcePath);
        if (! $sourceKey || ! Storage::disk($this->disk())->exists($sourceKey)) {
            throw new RuntimeException('複製元のStorageファイルが見つかりません。');
        }

        return $this->copyNormalizedKey(
            $sourceKey,
            $learningPageId,
            $learningSessionId,
            $learningStepId,
            $purpose,
        );
    }

    /**
     * 有効な複製元が存在する場合だけファイルをコピーする。
     *
     * blob:／data:はブラウザ内の一時値でStorageファイルではないため対象外とする。
     * 元ファイルが存在しない場合は既存挙動を維持し、例外ではなくnullを返す。
     */
    public function copyIfExists(
        ?string $sourcePath,
        int $learningPageId,
        int $learningSessionId,
        int $learningStepId,
        string $purpose
    ): ?string {
        $sourcePath = trim((string) $sourcePath);

        if ($sourcePath === ''
            || str_starts_with($sourcePath, 'blob:')
            || str_starts_with($sourcePath, 'data:')) {
            return null;
        }

        $sourceKey = $this->normalizeKey($sourcePath);
        if (! $sourceKey || ! $this->sourceExists($sourceKey)) {
            return null;
        }

        return $this->copyNormalizedKey(
            $sourceKey,
            $learningPageId,
            $learningSessionId,
            $learningStepId,
            $purpose,
        );
    }

    /**
     * 正規化済みかつ存在確認済みのStorageキーを複製する。
     * 呼び出し側でexists()済みのため、ここでは追加の存在確認を行わない。
     */
    private function copyNormalizedKey(
        string $sourceKey,
        int $learningPageId,
        int $learningSessionId,
        int $learningStepId,
        string $purpose
    ): string {
        $extension = strtolower(pathinfo($sourceKey, PATHINFO_EXTENSION) ?: 'bin');
        $destinationKey = $this->directory(
            $learningPageId,
            $learningSessionId,
            $learningStepId,
            $purpose,
        ) . '/' . Str::uuid()->toString() . '.' . $extension;

        $this->profile['copy_requests']++;
        $this->profile['copy_storage_calls']++;
        $startedAt = hrtime(true);

        try {
            if (! Storage::disk($this->disk())->copy($sourceKey, $destinationKey)) {
                throw new RuntimeException('Storageファイルの複製に失敗しました。');
            }

            $elapsedMs = $this->elapsedMilliseconds($startedAt);
            $this->profile['copy_ms'] += $elapsedMs;

            // 画像ごとのStorageコピー時間を記録し、遅延が全画像共通か特定画像だけかを判定する。
            Log::info('Learning media copy profile', [
                'success' => true,
                'disk' => $this->disk(),
                'source_key' => $sourceKey,
                'destination_key' => $destinationKey,
                'purpose' => $purpose,
                'copy_ms' => $elapsedMs,
            ]);
        } catch (\Throwable $exception) {
            $elapsedMs = $this->elapsedMilliseconds($startedAt);
            $this->profile['copy_ms'] += $elapsedMs;

            // 失敗したコピー元・コピー先も残し、Storage側の問題を追跡できるようにする。
            Log::error('Learning media copy profile', [
                'success' => false,
                'disk' => $this->disk(),
                'source_key' => $sourceKey,
                'destination_key' => $destinationKey,
                'purpose' => $purpose,
                'copy_ms' => $elapsedMs,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $destinationKey;
    }

    /**
     * 同一リクエスト内で蓄積したStorage存在確認結果を破棄する。
     *
     * 複製処理の開始時と終了時に呼び出し、別処理へ古い確認結果を持ち越さない。
     */
    public function clearRuntimeCache(): void
    {
        $this->existenceCache = [];
    }

    /**
     * 複製性能計測値を初期化する。
     *
     * Storage操作そのものには影響を与えず、今回の複製処理だけを計測対象にする。
     */
    public function resetProfile(): void
    {
        $this->profile = [
            'exists_requests' => 0,
            'exists_cache_hits' => 0,
            'exists_storage_calls' => 0,
            'exists_ms' => 0.0,
            'copy_requests' => 0,
            'copy_storage_calls' => 0,
            'copy_ms' => 0.0,
            'delete_requests' => 0,
        ];
    }

    /** @return array<string, int|float> */
    public function profile(): array
    {
        return $this->profile;
    }

    /**
     * 正規化済みStorageキーの存在有無を返す。
     *
     * 同じ元画像がmedia_pathとsettings内など複数箇所から参照される場合でも、
     * S3へのexists()通信は1回だけ実行し、以降は同一リクエスト内の結果を再利用する。
     */
    private function sourceExists(string $sourceKey): bool
    {
        $this->profile['exists_requests']++;

        if (array_key_exists($sourceKey, $this->existenceCache)) {
            $this->profile['exists_cache_hits']++;

            return $this->existenceCache[$sourceKey];
        }

        $this->profile['exists_storage_calls']++;
        $startedAt = hrtime(true);

        try {
            return $this->existenceCache[$sourceKey] = Storage::disk($this->disk())->exists($sourceKey);
        } finally {
            $this->profile['exists_ms'] += $this->elapsedMilliseconds($startedAt);
        }
    }

    public function delete(?string $path): void
    {
        $key = $this->normalizeKey($path);
        if ($key) {
            $this->profile['delete_requests']++;
            Storage::disk($this->disk())->delete($key);
        }
    }

    public function deleteDirectory(string $directory): void
    {
        Storage::disk($this->disk())->deleteDirectory(trim($directory, '/'));
    }

    /**
     * 学習メディア用のStorageディスク名を返す。
     * Xserver移行時は設定値だけで切り替えられる構造にする。
     */

    /**
     * hrtime()の差分をミリ秒へ変換する。
     */
    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    private function disk(): string
    {
        return (string) config('filesystems.learning_media_disk', 's3');
    }

    private function directory(
        int $learningPageId,
        int $learningSessionId,
        int $learningStepId,
        string $purpose
    ): string {
        $allowed = ['description', 'example', 'answer', 'commentary', 'media', 'pdf', 'background'];
        $purpose = in_array($purpose, $allowed, true) ? $purpose : 'media';

        return "learning-pages/{$learningPageId}/sessions/{$learningSessionId}/steps/{$learningStepId}/{$purpose}";
    }
}
