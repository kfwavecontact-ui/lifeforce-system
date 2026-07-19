<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 掲示板添付ファイルのStorage操作を一元管理するService。
 * LearningMediaServiceと同じStorageディスク設定を利用します。
 */
class BoardMediaService
{
    public function upload(UploadedFile $file, int $boardPostId): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $key = 'boards/' . $boardPostId . '/files/' . Str::uuid() . '.' . $extension;
        $stored = Storage::disk($this->disk())->putFileAs(dirname($key), $file, basename($key));

        if (! $stored || ! Storage::disk($this->disk())->exists($stored)) {
            throw new RuntimeException('掲示板添付ファイルの保存に失敗しました。Storage設定と書き込み権限を確認してください。');
        }

        return $stored;
    }

    public function copy(string $sourceKey, int $boardPostId): string
    {
        $extension = strtolower(pathinfo($sourceKey, PATHINFO_EXTENSION) ?: 'bin');
        $destination = 'boards/' . $boardPostId . '/files/' . Str::uuid() . '.' . $extension;

        if (! Storage::disk($this->disk())->exists($sourceKey)
            || ! Storage::disk($this->disk())->copy($sourceKey, $destination)) {
            throw new RuntimeException('掲示板添付ファイルの複製に失敗しました。');
        }

        return $destination;
    }

    public function delete(?string $key): void
    {
        if ($key) {
            Storage::disk($this->disk())->delete($key);
        }
    }

    public function url(?string $key): string
    {
        if (! $key) {
            return '';
        }

        try {
            return Storage::disk($this->disk())->temporaryUrl($key, now()->addHours(6));
        } catch (\Throwable) {
            return Storage::disk($this->disk())->url($key);
        }
    }

    private function disk(): string
    {
        return (string) config('filesystems.learning_media_disk', 's3');
    }
}
