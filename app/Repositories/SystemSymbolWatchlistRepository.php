<?php

namespace App\Repositories;

use App\Models\SystemSymbolWatchlist;

class SystemSymbolWatchlistRepository extends BaseRepository
{
    public function __construct(protected SystemSymbolWatchlist $model)
    {

    }

    public function getSystemSymbolWatchlist()
    {
        return $this->getModel()->all();
    }
}
