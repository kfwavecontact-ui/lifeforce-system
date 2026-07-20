<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 一斉通知添付ファイルの保存・複製・削除・URL生成を担当します。
 */
class NotificationMediaService
{
    private string $disk = 's3';

    public function store(UploadedFile $file, int $notificationId): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $key = sprintf('notifications/%d/files/%s.%s', $notificationId, Str::uuid(), $extension);
        Storage::disk($this->disk)->put($key, $file->getContent(), ['visibility' => 'private']);
        return $key;
    }

    public function copy(string $sourceKey, int $notificationId): string
    {
        $extension = pathinfo($sourceKey, PATHINFO_EXTENSION) ?: 'bin';
        $destination = sprintf('notifications/%d/files/%s.%s', $notificationId, Str::uuid(), $extension);
        Storage::disk($this->disk)->copy($sourceKey, $destination);
        return $destination;
    }

    public function delete(string $key): void
    {
        if ($key !== '') {
            Storage::disk($this->disk)->delete($key);
        }
    }

    public function url(string $key): string
    {
        return Storage::disk($this->disk)->temporaryUrl($key, now()->addMinutes(30));
    }
}
