<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSchedule extends Model
{
    protected $fillable = [
        'event_id',
        'start_at',
        'end_at',
        'capacity',
        'min_participants',
        'waitlist_enabled',
        'waitlist_capacity',
        'application_start_at',
        'application_deadline_at',
        'cancel_deadline_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'application_start_at' => 'datetime',
        'application_deadline_at' => 'datetime',
        'cancel_deadline_at' => 'datetime',
        'waitlist_enabled' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function applications()
    {
        return $this->hasMany(EventApplication::class, 'event_schedule_id');
    }

}