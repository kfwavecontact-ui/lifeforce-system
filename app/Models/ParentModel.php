<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentModel extends Model
{
    protected $table = 'parents';

    protected $fillable = [
        'user_id',
        'parent_code',
        'last_name',
        'first_name',
        'phone_number',
        'postal_code',
        'address',
        'is_emergency_contact',
        'emergency_priority',
        'occupation',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}