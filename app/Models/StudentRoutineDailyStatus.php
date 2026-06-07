<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentRoutineDailyStatus extends Model
{
    protected $fillable = [
        'student_id',
        'student_routine_item_id',
        'target_date',
        'status',
        'achieved_days',
        'elapsed_days',
        'achievement_rate',
        'studied_at',
        'study_seconds',
        'material_read_at',
        'video_watched_at',
        'approved_at',
        'approved_by',
        'comment',
    ];

    protected $casts = [
        'target_date' => 'date',
        'studied_at' => 'datetime',
        'material_read_at' => 'datetime',
        'video_watched_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(StudentRoutineItem::class, 'student_routine_item_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}