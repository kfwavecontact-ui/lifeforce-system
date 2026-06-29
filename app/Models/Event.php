<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EventSchedule;


class Event extends Model
{
    protected $fillable = [
        'event_category_id',
        'event_status_id',
        'title',
        'description',
        'organizer_type',
        'organizer_user_id',
        'is_paid',
        'is_external_allowed',
        'is_active',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_external_allowed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function titles()
    {
        return $this->belongsToMany(
            Title::class,
            'title_event_relations',
            'event_id',
            'title_id'
        );
    }

    public function schedules()
    {
        return $this->hasMany(EventSchedule::class);
    }
}