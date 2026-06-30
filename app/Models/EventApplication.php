<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventApplication extends Model
{
    protected $fillable = [
        'event_id',
        'event_schedule_id',
        'school_id',
        'student_id',
        'event_price_id',
        'applicant_user_id',
        'participant_type',
        'application_status',
        'applied_at',
        'cancelled_at',
        'cancelled_reason',
        'memo',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EventSchedule::class, 'event_schedule_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(EventPrice::class, 'event_price_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EventPayment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(EventRefund::class);
    }
}
