<?php

namespace App\Feature\SystemSymbolWatchlist\Jobs;

use App\Feature\OptionsDataSync\Jobs\SyncSymbolOptionsDataJob;
use App\Repositories\SystemSymbolWatchlistRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncSystemSymbolWatchlistOptionsDataJob implements ShouldQueue, ArtisanDispatchable
{
    use Queueable;

    public function handle(SystemSymbolWatchlistRepository $systemSymbolWatchlistRepository)
    {
        $systemSymbolWatchlist = $systemSymbolWatchlistRepository->getQuery()->get();
        foreach ($systemSymbolWatchlist as $symbolRow) {
            dispatch(new SyncSymbolOptionsDataJob($symbolRow->symbol))->onQueue('options-data-sync');
        }
    }
}
