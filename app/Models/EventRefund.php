<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRefund extends Model
{
    protected $fillable = [
        'event_application_id',
        'event_payment_id',
        'account_transaction_id',
        'refund_method_id',
        'scheduled_refund_date',
        'payment_provider',
        'provider_refund_id',
        'refund_amount',
        'refund_status',
        'refund_reason',
        'refunded_at',
        'handled_by',
        'raw_response',
        'memo',
    ];

    protected $casts = [
        'scheduled_refund_date' => 'date',
        'refunded_at' => 'datetime',
        'refund_amount' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(EventApplication::class, 'event_application_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(EventPayment::class, 'event_payment_id');
    }

    public function accountTransaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'account_transaction_id');
    }

    public function refundMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'refund_method_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
