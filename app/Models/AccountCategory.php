<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'transaction_type',
        'sort_order',
        'is_active',
        'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }
}