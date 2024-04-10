<?php

namespace App\Repositories;

use App\Models\OpenAIChatLog;

class OpenAIChatLogRepository extends BaseRepository
{
    public function __construct(protected OpenAIChatLog $model)
    {
    }
}
