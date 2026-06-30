<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPayment extends Model
{
    protected $fillable = [
        'event_application_id',
        'account_transaction_id',
        'payment_method_id',
        'scheduled_payment_date',
        'payment_provider',
        'payment_method',
        'provider_payment_id',
        'provider_checkout_id',
        'provider_customer_id',
        'amount',
        'currency',
        'payment_status',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'raw_response',
        'memo',
    ];

    protected $casts = [
        'scheduled_payment_date' => 'date',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(EventApplication::class, 'event_application_id');
    }

    public function accountTransaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'account_transaction_id');
    }

    public function paymentMethodMaster(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(EventRefund::class);
    }
}
