<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudySession extends Model
{
    protected $fillable = [
        'student_id',
        'learning_content_id',
        'routine_content_id',
        'learning_session_id',
        'student_routine_item_id',
        'component_type',
        'completion_key',
        'started_at',
        'ended_at',
        'actual_minutes',
        'is_completed',
        'last_activity_at',
        'context',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_completed' => 'boolean',
        'context' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function learningContent(): BelongsTo
    {
        return $this->belongsTo(LearningContent::class);
    }

    public function studentRoutineItem(): BelongsTo
    {
        return $this->belongsTo(StudentRoutineItem::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(StudyResult::class);
    }
}
