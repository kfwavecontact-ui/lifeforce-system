<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPrice extends Model
{
    protected $fillable = [
        'event_id',
        'participant_type',
        'price',
        'currency',
        'is_free',
    ];

    protected $casts = [
        'price' => 'integer',
        'is_free' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(EventApplication::class);
    }
}
