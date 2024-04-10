<?php

namespace App\Feature\EarningsWatcher\Jobs;

use App\Feature\ThetaData\Services\ThetaDataAPI;
use App\Job\BasicJob;
use App\Repositories\EarningsWatcherListRepository;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncEarningsStockPrice extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 3;

    public int $backoff = 300;

    public string $artisanName = 'earnings:sync-price';

    /**
     * @var EarningsWatcherListRepository
     */
    private mixed $earningsWatcherListRepository;

    /**
     * @var ThetaDataAPI
     */
    private mixed $thetaDataAPI;

    public function handle(): int
    {
        Log::info('SyncEarningsStockPrice - Start');

        $this->createServices();

        $pendingEarnings = $this->earningsWatcherListRepository->getPendingEarnings();

        if($pendingEarnings->isEmpty()) {
            Log::info('SyncEarningsStockPrice - No pending earnings');
            return 0;
        }

        Log::info('SyncEarningsStockPrice - Pending earnings found', [
            'count' => $pendingEarnings->count()
        ]);

        $pendingEarnings->each(function($pendingEarning) {
            try {
                $closePrice = $this->thetaDataAPI->getStockLastEODClose($pendingEarning->symbol);
                $pendingEarning->eod_price = $closePrice;
                $pendingEarning->save();
            } catch (\Exception $e) {
                Log::error('SyncEarningsStockPrice - Error updating stock price', [
                    'symbol' => $pendingEarning->symbol,
                    'earnings_watcher_list_id' => $pendingEarning->id,
                    'error' => $e->getMessage()
                ]);
            }
        });

        Log::info('SyncEarningsStockPrice - Pending earnings updated', [
            'count' => $pendingEarnings->count()
        ]);

        return 0;
    }

    public function createServices(): void
    {
        $this->earningsWatcherListRepository = app(EarningsWatcherListRepository::class);
        $this->thetaDataAPI = app(ThetaDataAPI::class);
    }

}
