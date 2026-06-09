<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = [
        'student_id',
        'event_type',
        'event_type_name',
        'point_rule_code',
        'points',
        'balance_after',
        'reason',
        'related_id',
        'occurred_at',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}