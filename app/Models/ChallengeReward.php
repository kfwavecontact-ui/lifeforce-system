<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeReward extends Model
{
    protected $fillable = [
        'challenge_id',
        'badge_id',
        'title_id',
        'point_amount',
        'sort_order',
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    public function title()
    {
        return $this->belongsTo(Title::class);
    }
}