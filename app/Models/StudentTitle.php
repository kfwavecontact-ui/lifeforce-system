<?php

namespace App\Models;

use App\Enums\TitleGrantMethod;
use App\Enums\TitleRemovalReason;
use App\Enums\StudentTitleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 生徒への1回の称号付与と現在状態を保持するモデル。
 *
 * 関連画面: わくわく > 称号 > 獲得履歴
 * 利用DB: student_titles（参照・更新）
 */
class StudentTitle extends Model
{
    protected $fillable = [
        'student_id',
        'title_id',
        'status',
        'grant_method',
        'acquired_at',
        'granted_by',
        'grant_reason',
        'removed_at',
        'removed_by',
        'removal_reason_code',
        'removal_reason_detail',
        'regrant_source_id',
        'grant_notification_sent',
        'removal_notification_sent',
        'is_displayed',
        'is_equipped',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentTitleStatus::class,
            'grant_method' => TitleGrantMethod::class,
            'removal_reason_code' => TitleRemovalReason::class,
            'acquired_at' => 'datetime',
            'removed_at' => 'datetime',
            'grant_notification_sent' => 'boolean',
            'removal_notification_sent' => 'boolean',
            'is_displayed' => 'boolean',
            'is_equipped' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function regrantSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'regrant_source_id');
    }

    public function regrants(): HasMany
    {
        return $this->hasMany(self::class, 'regrant_source_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TitleHistory::class)->orderBy('event_at');
    }

    public function isActive(): bool
    {
        return $this->status === StudentTitleStatus::ACTIVE;
    }
}
