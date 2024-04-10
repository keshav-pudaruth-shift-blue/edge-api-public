<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class OpenAIChatPrompts extends BaseModel
{
    use SoftDeletes;

    public const ROLE_SYSTEM = 'system';
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    public const CONTEXT_TWITTER_TRADES = 'twitter_trades';

    protected $table = 'openai_chat_prompts';

    protected $fillable = [
        'role',
        'content',
        'is_active'
    ];
}
