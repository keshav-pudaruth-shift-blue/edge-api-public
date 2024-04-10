<?php

namespace App\Repositories;

use App\Models\TwitterFollowing;

class TwitterFollowingRepository extends BaseRepository
{
    public function __construct(protected TwitterFollowing $model)
    {
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveFollowing(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()
            ->where('is_active', '=', true)
            ->get();
    }
}
