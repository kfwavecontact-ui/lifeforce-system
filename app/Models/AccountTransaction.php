<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Discount;
use App\Enums\AccountTransactionSourceType;

class AccountTransaction extends Model
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'scheduled_date',
        'transaction_date',
        'account_category_id',
        'payment_method_id',
        'transaction_name',
        'amount',

        'before_discount_amount',
        'discount_amount',
        'discount_type_id',
        'discount_note',
        
        'status',
        'cancelled_reason',
        'memo',
        'school_id',
        'student_id',
        'source_table',
        'source_id',
        'related_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'transaction_date' => 'date',
        'amount' => 'integer',
        'source_table' => AccountTransactionSourceType::class,
    ];

    public function accountCategory()
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function discountType()
    {
        return $this->belongsTo(Discount::class, 'discount_type_id');
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}