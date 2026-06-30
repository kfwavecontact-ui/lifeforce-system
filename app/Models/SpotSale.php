<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SpotSale extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'payment_method_id',
        'sale_code',
        'category',
        'sale_title',
        'quantity',
        'sale_date',
        'payment_due_date',
        'paid_at',
        'before_discount_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'payment_status',
        'memo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'payment_due_date' => 'date',
        'paid_at' => 'datetime',
        'quantity' => 'integer',
        'before_discount_amount' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function accountTransaction(): HasOne
    {
        return $this->hasOne(AccountTransaction::class, 'source_id')
            ->where('source_table', 'spot_sales');
    }
}