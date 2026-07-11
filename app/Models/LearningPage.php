<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningPage extends Model
{
    protected $fillable = [
        'routine_content_id',
        'version',
        'time_limit_seconds',
        'attempt_limit',
        'is_random',
        'allow_resume',
        'bgm_enabled',
        'sound_enabled',
        'status',
        'publication_status',
        'publish_start_at',
        'publish_end_at',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_random' => 'boolean',
        'allow_resume' => 'boolean',
        'bgm_enabled' => 'boolean',
        'sound_enabled' => 'boolean',
        'publish_start_at' => 'datetime',
        'publish_end_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function routineContent(): BelongsTo
    {
        return $this->belongsTo(RoutineContent::class, 'routine_content_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LearningSession::class)->orderBy('sort_order')->orderBy('session_no');
    }
}
