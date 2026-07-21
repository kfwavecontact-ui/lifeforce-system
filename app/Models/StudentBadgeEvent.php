<?php

namespace App\Models;

use App\Enums\BadgeRemovalReason;
use App\Enums\StudentBadgeEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 生徒バッジの操作履歴モデル。
 *
 * 関連画面: わくわく > バッジ > 獲得履歴 > 詳細
 * 利用DB: student_badge_events（参照・追加）
 */
class StudentBadgeEvent extends Model
{
    protected $fillable = [
        'student_badge_id',
        'student_id',
        'badge_id',
        'event_type',
        'operated_by',
        'reason_code',
        'reason_detail',
        'event_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => StudentBadgeEventType::class,
            'reason_code' => BadgeRemovalReason::class,
            'event_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function studentBadge(): BelongsTo
    {
        return $this->belongsTo(StudentBadge::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operated_by');
    }
}
