<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineStudyResult extends Model
{
    protected $fillable = [
        'routine_study_session_id',
        'total_questions',
        'correct_answers',
        'incorrect_answers',
        'accuracy_rate',
        'score',
        'rank',
        'stars',
        'total_elapsed_ms',
        'hint_count',
        'used_answer',
        'details',
    ];

    protected $casts = [
        'accuracy_rate' => 'decimal:2',
        'used_answer' => 'boolean',
        'details' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RoutineStudySession::class, 'routine_study_session_id');
    }
}
