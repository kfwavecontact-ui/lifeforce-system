<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyResult extends Model
{
    protected $fillable = [
        'study_session_id',
        'total_questions',
        'correct_answers',
        'incorrect_answers',
        'accuracy_rate',
        'streak_correct_count',
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

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }
}
