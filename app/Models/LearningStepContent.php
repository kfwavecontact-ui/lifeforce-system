<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningStepContent extends Model
{
    protected $fillable = [
        'learning_step_id',
        'content_title',
        'body',
        'media_type',
        'media_path',
        'settings',
        'questions',
    ];

    protected $casts = [
        'settings' => 'array',
        'questions' => 'array',
    ];

    public function learningStep(): BelongsTo
    {
        return $this->belongsTo(LearningStep::class);
    }
}
