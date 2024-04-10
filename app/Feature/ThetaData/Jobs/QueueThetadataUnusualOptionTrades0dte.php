<?php

namespace App\Feature\ThetaData\Jobs;

use App\Feature\ThetaData\Services\ThetaDataAPI;
use App\Job\BasicJob;
use App\Models\OptionsChainWatchlist;
use App\Repositories\OptionsChainWatchlistRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class QueueThetadataUnusualOptionTrades0dte extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 2;

    public $queue = 'thetadata';

    public string $artisanName = 'thetadata:queue-trade-0dte';

    protected string $scanType = OptionsChainWatchlist::WATCHLIST_TYPE_0DTE;

    /**
     * @var OptionsChainWatchlistRepository
     */
    private $optionsChainWatchlistRepository;

    /**
     * @var ThetaDataAPI
     */
    private $thetaDataAPI;

    public function handle(): void
    {
        $this->optionsChainWatchlistRepository = app(OptionsChainWatchlistRepository::class);
        $this->thetaDataAPI = app(ThetaDataAPI::class);

        //Get list of symbols to scan
        $optionChainWatchlists = $this->optionsChainWatchlistRepository->getActive0dte();

        $todayDate = now()->format('Ymd');

        foreach ($optionChainWatchlists as $optionChainWatchlistRow) {
            $expirations = Cache::remember('thetadata-option-expiration-'.$optionChainWatchlistRow->symbol, now()->addWeek(),function () use ($optionChainWatchlistRow, $todayDate) {
                return $this->thetaDataAPI->getExpirations($optionChainWatchlistRow->symbol)->json('response');
            });

            //Today is an expiry date
            if(in_array($todayDate, $expirations) === true) {
                dispatch(new AnalyzeThetadataUnusualOptionTrade($optionChainWatchlistRow->symbol, $todayDate, $optionChainWatchlistRow->volume_alert_0dte, $this->scanType));
            } else {
                Log::debug('QueueThetadataUnusualOptionTrades0dte::handle - Skipping 0dte symbol', [
                    'symbol' => $optionChainWatchlistRow->symbol,
                    'expiryDate' => $expirations[0],
                    'todayDate' => $todayDate,
                ]);
            }
        }
    }
}
