<?php

namespace App\Repositories;

use App\Models\EarningsWatcherUnusualTrades;

class EarningsWatcherUnusualTradesRepository extends BaseRepository
{
    public function __construct(protected EarningsWatcherUnusualTrades $model) {}
}
