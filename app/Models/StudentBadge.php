<?php

namespace App\Models;

use App\Enums\BadgeGrantMethod;
use App\Enums\BadgeRemovalReason;
use App\Enums\StudentBadgeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 生徒への1回のバッジ付与と現在状態を保持するモデル。
 *
 * 関連画面: わくわく > バッジ > 獲得履歴
 * 利用DB: student_badges（参照・更新）
 */
class StudentBadge extends Model
{
    protected $fillable = [
        'student_id',
        'badge_id',
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
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentBadgeStatus::class,
            'grant_method' => BadgeGrantMethod::class,
            'removal_reason_code' => BadgeRemovalReason::class,
            'acquired_at' => 'datetime',
            'removed_at' => 'datetime',
            'grant_notification_sent' => 'boolean',
            'removal_notification_sent' => 'boolean',
            'is_displayed' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
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
        return $this->hasMany(StudentBadgeEvent::class)->orderBy('event_at');
    }

    public function isActive(): bool
    {
        return $this->status === StudentBadgeStatus::ACTIVE;
    }
}
