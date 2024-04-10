<?php

namespace App\Repositories;

class UsersWhitelist extends BaseRepository
{
    public function __construct(protected \App\Models\UsersWhitelist $model){}

    /**
     * @param string $email
     * @return bool
     */
    public function isWhitelisted(string $email): bool
    {
        return $this->getQuery()->where('email', $email)->exists();
    }
}
