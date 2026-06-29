<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'student_id',
        'payment_method_id',
        'payment_date',
        'payment_amount',
        'payment_status',
        'transaction_no',
        'confirmed_by',
        'confirmed_at',
        'note',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}