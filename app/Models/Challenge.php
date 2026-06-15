<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    protected $fillable = [
        'challenge_category_id',
        'code',
        'name',
        'difficulty',
        'description',
        'icon_path',
        'challenge_type',
        'requirement_description',
        'max_score',
        'passing_score',
        'sort_order',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(ChallengeCategory::class, 'challenge_category_id');
    }

    public function rewards()
    {
        return $this->hasMany(ChallengeReward::class);
    }

    public function reservations()
    {
        return $this->hasMany(ChallengeReservation::class);
    }

    public function logs()
    {
        return $this->hasMany(ChallengeLog::class);
    }
}