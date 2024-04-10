<?php

namespace App\Feature\EarningsWatcher\Jobs;

class SyncEarningsUnusualTradesRush extends SyncEarningsUnusualTrades
{
    public int $tries = 3;

    public int $backoff = 30;

    public string $artisanName = 'earnings:sync-unusual-trades-rush';

    public string $cacheLockPrefix = 'earnings_watcher_unusual_trades_rush';

    public int $symbolCacheLockDuration = 1;

    public int $unusualTradeCacheLockDuration = 86400; //1 day

    protected function getPendingEarnings(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->earningsWatcherListRepository->getPendingEarningsRush(10.0);
    }
}
