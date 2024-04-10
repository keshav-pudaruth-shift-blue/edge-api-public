<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TwitterFollowing extends BaseModel
{
    use HasFactory;

    public $table = 'twitter_following';

    protected $fillable = [
        'username',
        'interval',
        'last_tweet_datetime',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected $dates = [
        'last_tweet_datetime'
    ];
}
