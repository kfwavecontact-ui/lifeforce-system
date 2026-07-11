<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningSession extends Model
{
    protected $fillable = [
        'learning_page_id',
        'session_no',
        'title',
        'subtitle',
        'show_subtitle',
        'developer_note',
        'development_status',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'show_subtitle' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function learningPage(): BelongsTo
    {
        return $this->belongsTo(LearningPage::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(LearningStep::class)->orderBy('sort_order');
    }
}
