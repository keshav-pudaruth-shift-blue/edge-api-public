<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use App\Models\SymbolList;
use App\Repositories\HolidayRepository;
use App\Repositories\OptionsContractsWithTradingHoursRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncSymbolOptionsContractGreeksSpecificExpiryJob extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 30;

    /**
     * @var OptionsContractsWithTradingHoursRepository
     */
    private $optionsContractsRepository;

    /**
     * @var IBGatewayService
     */
    private $ibGatewayService;

    /**
     * @var HolidayRepository
     */
    private $holidayRepository;

    /**
     * @param SymbolList $symbol
     * @param string $expiry
     * @param int $updateInterval
     * @param int $strikeRange
     */
    public function __construct(
        protected string $symbol,
        protected string $expiry,
        protected int $updateInterval,
        protected int $strikeRange
    ) {
        $this->expiry = Carbon::createFromFormat('Y-m-d', $expiry);
    }

    /**
     * @throws \Throwable
     */
    public function handle()
    {
        $this->initializeServices();

        //Get all options contracts for symbol and expiry
        $optionsContracts = $this->optionsContractsRepository->getBySymbolAndExpiry($this->symbol, $this->expiry);

        if(empty($optionsContracts)) {
            Log::info('SyncSymbolOptionsContractSpecificExpiryJob - No options contracts found for symbol', [
                'symbol' => $this->symbol,
                'expiry' => $this->expiry->format('Y-m-d')
            ]);
            //Fetch optionsContracts
            $this->fetchAndSaveOptionsContractsFromIB();
        }

        //Fetch one option contract to get underlying price
        if ($this->attempts() > 1) {
            $optionContract = $optionsContracts->random();
        } else {
            $optionContract = $optionsContracts->first();
        }

        //Fetch n remember underlying price
        //Cache expires right before next option contract refresh
        $underlyingPrice = Cache::remember(
            'live-underlying-symbol-price-' . $this->symbol,
            $this->updateInterval - 5,
            function () use ($optionContract) {
                //TODO: fix this
                $underlyingPrice = $this->ibGatewayService->getSymbolUnderlyingPrice(
                    $optionContract->symbol,
                    $optionContract->expiry_date,
                    $optionContract->strike_price);

                if (empty($underlyingPrice)) {
                    Log::warning('SyncSymbolOptionsContractSpecificExpiryJob::handle - No option contract data found', [
                        'symbol' => $this->symbol,
                        'expiry' => $this->expiry,
                        'strike_price' => $optionContract->strike_price
                    ]);

                    $this->release(5);
                    return 0;
                }

                return $underlyingPrice;
            }
        );

        //Exit if no underlying price is found
        if ($underlyingPrice === 0) {
            Log::warning('SyncSymbolOptionsContractSpecificExpiryJob::handle - No underlying price found', [
                'symbol' => $this->symbol,
            ]);
            return;
        }

        $optionsContractsToFetch = $this->getContractList($underlyingPrice);

        $optionsContractsWithMetaData = collect();

        $optionsContractsToFetch->chunk(config('ib-gateway.max_connections'))->each(function ($optionsContractsChunk) use ($optionsContractsWithMetaData) {
            $optionsContractsWithMetaData->merge($this->ibGatewayService->getPoolOptionContractByStrike($optionsContractsChunk));
        });

        $data = $optionsContractsWithMetaData->toArray();

        return;
    }

    private function initializeServices()
    {
        //Initialize services
        $this->optionsContractsRepository = app(OptionsContractsWithTradingHoursRepository::class);
        $this->ibGatewayService = app(IBGatewayService::class);
        $this->holidayRepository = app(HolidayRepository::class);
    }

    /**
     * @param $underlyingPrice
     * @return Collection
     */
    private function getContractList($underlyingPrice): Collection
    {
        //Get option strike range for update
        $startStrike = $underlyingPrice - $this->strikeRange;
        $endStrike = $underlyingPrice + $this->strikeRange;

        if($startStrike <= 0 ){
            $startStrike = 0;
        }

        //Get option contract list
        return $this->optionsContractsRepository->getBySymbolAndExpiryAndStrikeRange(
            $this->symbol,
            $this->expiry,
            $startStrike,
            $endStrike
        );

//        $dte = Carbon::createFromFormat('Y-m-d', $this->expiry)->diffInDaysFiltered(function (Carbon $date) {
//            return !$date->isWeekend() && !$date->isHoliday();
//        }, Carbon::now());
    }

    private function fetchAndSaveOptionsContractsFromIB(): Collection
    {
        $optionContracts = $this->ibGatewayService->getOptionsContracts($this->symbol, $this->expiry);
    }

}
