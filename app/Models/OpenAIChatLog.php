<?php

namespace App\Models;

class OpenAIChatLog extends BaseModel
{
    protected $table = 'openai_chat_log';

    protected $fillable = [
        'request',
        'response',
        'context',
        'total_tokens'
    ];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];
}
