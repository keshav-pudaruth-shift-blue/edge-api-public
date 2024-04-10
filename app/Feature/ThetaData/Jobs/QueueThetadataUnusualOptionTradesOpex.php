<?php

namespace App\Feature\ThetaData\Jobs;

use App\Feature\ThetaData\Services\ThetaDataAPI;
use App\Job\BasicJob;
use App\Models\OptionsChainWatchlist;
use App\Repositories\OptionsChainWatchlistRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class QueueThetadataUnusualOptionTradesOpex extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 2;

    public $queue = 'thetadata';

    public string $artisanName = 'thetadata:queue-trade-opex';

    protected string $scanType = OptionsChainWatchlist::WATCHLIST_TYPE_OPEX;

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

            $opexDate = Cache::remember('thetadata-option-expiration-opex', now()->setHour(20),function () use ($expirations,$optionChainWatchlistRow, $todayDate) {
                $thisMonthOpex = Carbon::parse("Third friday of this month");
                $nextMonthOpex = Carbon::parse("Third friday of next month");
                //Check if we are past opex for this month
                if(now()->addDays(2)->lt($thisMonthOpex) === true) {
                    $selectedMonthOpexYmd = $thisMonthOpex->format('Ymd');
                } else {
                    $selectedMonthOpexYmd = $nextMonthOpex->format('Ymd');
                }

                $remainingExpirations = array_filter($expirations, function($expiration) use ($selectedMonthOpexYmd) {
                    return $expiration >= $selectedMonthOpexYmd;
                });

                Log::info('QueueThetadataUnusualOptionTradesOpex - Computed Opex date', ['opex' => $remainingExpirations[0]]);

                return $remainingExpirations[0];
            });

            if(!empty($opexDate)) {
                dispatch(new AnalyzeThetadataUnusualOptionTrade($optionChainWatchlistRow->symbol, $opexDate, $optionChainWatchlistRow->volume_alert_opex, $this->scanType));
            } else {
                Log::error('QueueThetadataUnusualOptionTradesOpex - No opex date found');
            }
        }
    }
}
