<?php

namespace App\Repositories;

use App\Models\OpenAIChatPrompts;

class OpenAIChatPromptsRepository extends BaseRepository
{
    public function __construct(protected OpenAIChatPrompts $model)
    {
    }

    /**
     * @param string $context
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function getByContext(string $context): \Illuminate\Database\Eloquent\Collection|array
    {
        return $this->getQuery()
            ->where('is_active', '=', true)
            ->where('context', $context)
            ->orderBy('id', 'ASC')->get();
    }
}
