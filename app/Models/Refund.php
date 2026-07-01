<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'refund_code',
        'refund_source_type',
        'refund_source_id',
        'refund_amount',
        'refund_method_id',
        'refund_reason',
        'scheduled_date',
        'refunded_at',
        'status',
        'memo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'refunded_at' => 'date',
        'refund_amount' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function refundMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'refund_method_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}