<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LearningMediaService
{
    private const DISK = 's3';

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
        $bucket = trim((string) config('filesystems.disks.s3.bucket'));

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
            return Storage::disk(self::DISK)->temporaryUrl($key, now()->addHours(6));
        } catch (\Throwable) {
            return Storage::disk(self::DISK)->url($key);
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
        $storedKey = Storage::disk(self::DISK)->putFileAs($directory, $file, $fileName);

        if (! $storedKey || ! Storage::disk(self::DISK)->exists($storedKey)) {
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
        if (! $sourceKey || ! Storage::disk(self::DISK)->exists($sourceKey)) {
            throw new RuntimeException('複製元のS3ファイルが見つかりません。');
        }

        $extension = strtolower(pathinfo(parse_url($sourceKey, PHP_URL_PATH) ?: $sourceKey, PATHINFO_EXTENSION) ?: 'bin');
        $destinationKey = $this->directory($learningPageId, $learningSessionId, $learningStepId, $purpose)
            . '/' . Str::uuid()->toString() . '.' . $extension;

        if (! Storage::disk(self::DISK)->copy($sourceKey, $destinationKey)
            || ! Storage::disk(self::DISK)->exists($destinationKey)) {
            throw new RuntimeException('S3ファイルの複製に失敗しました。');
        }

        return $destinationKey;
    }

    public function delete(?string $path): void
    {
        $key = $this->normalizeKey($path);
        if ($key) {
            Storage::disk(self::DISK)->delete($key);
        }
    }

    public function deleteDirectory(string $directory): void
    {
        Storage::disk(self::DISK)->deleteDirectory(trim($directory, '/'));
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
