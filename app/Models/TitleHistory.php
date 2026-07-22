<?php

namespace App\Models;

use App\Enums\TitleRemovalReason;
use App\Enums\TitleHistoryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 生徒称号の操作履歴モデル。
 *
 * 関連画面: わくわく > 称号 > 獲得履歴 > 詳細
 * 利用DB: title_histories（参照・追加）
 */
class TitleHistory extends Model
{
    protected $fillable = [
        'student_title_id',
        'student_id',
        'title_id',
        'event_type',
        'operated_by',
        'reason_code',
        'reason_detail',
        'event_at',
        'is_equipped',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => TitleHistoryType::class,
            'reason_code' => TitleRemovalReason::class,
            'event_at' => 'datetime',
            'is_equipped' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function studentTitle(): BelongsTo
    {
        return $this->belongsTo(StudentTitle::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operated_by');
    }
}
