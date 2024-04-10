<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class UsersSocialProviders extends BaseModel
{
    use HasFactory;

    protected $table = 'users_social_providers';

    protected $fillable = [
        'provider',
        'provider_user_id',
        'user_id',
        'discord_guilds',
    ];

    protected $casts = [
        'discord_guilds' => 'array',
    ];

    static public array $socialProviderList = [
        SocialProvider::DISCORD,
        SocialProvider::REDDIT,
        SocialProvider::GOOGLE,
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
