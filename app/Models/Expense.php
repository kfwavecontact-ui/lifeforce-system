<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'school_id',
        'expense_code',
        'expense_category',
        'expense_title',
        'vendor_name',
        'scheduled_date',
        'paid_at',
        'payment_method_id',
        'amount',
        'tax_amount',
        'total_amount',
        'payment_status',
        'memo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'paid_at' => 'date',
        'amount' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'expense_category' => ExpenseCategory::class,
        'payment_status' => ExpenseStatus::class,
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function accountTransaction()
    {
        return $this->hasOne(AccountTransaction::class, 'source_id')
            ->where('source_table', 'expense');
    }
}
