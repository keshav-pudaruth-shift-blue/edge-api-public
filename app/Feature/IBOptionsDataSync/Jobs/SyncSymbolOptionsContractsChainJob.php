<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Feature\IBOptionsDataSync\Services\TradingHoursService;
use App\Job\BasicJob;
use App\Models\OptionsContractsTradingHours;
use App\Models\OptionsContractsTypeEnum;
use App\Models\SymbolList;
use App\Repositories\HolidayRepository;
use App\Repositories\OptionsContractsRepository;
use App\Repositories\OptionsContractsTradingHoursRepository;
use App\Repositories\OptionsContractsWithTradingHoursRepository;
use App\Services\StockService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncSymbolOptionsContractsChainJob
 * @package App\Feature\IBOptionsDataSync\Jobs
 * @description This job is responsible for syncing options contracts chain for a given symbol for each expiration date
 */
class SyncSymbolOptionsContractsChainJob extends BasicJob
{
    public int $timeout = 0;

    /**
     * @var IBGatewayService
     */
    private $ibGatewayService;

    /**
     * @var HolidayRepository
     */
    private $holidayRepository;

    /**
     * @var OptionsContractsRepository
     */
    private $optionsContractsRepository;

    /**
     * @var OptionsContractsTradingHoursRepository
     */
    private $optionsContractsTradingHoursRepository;

    /**
     * @var TradingHoursService
     */
    private $tradingHoursService;

    /**
     * @var StockService
     */
    private $stockService;

    public function __construct(
        public SymbolList $symbol,
    ) {
    }

    public function handle()
    {
        $this->initializeServices();

        $contracts = $this->fetchExpiries();

        Log::debug('Contracts Chain:', [$contracts]);

        if (!empty($contracts)) {
            //Get first chain in contract
            $validContractChain = Arr::first($contracts);

            //Loop through each expiration date
            foreach ($validContractChain['expirations'] as $key => $expirationDate) {
                $expirationDate = Carbon::createFromFormat('Ymd', $expirationDate, 'UTC');

                //Check if expiration date is in the future & expiration date is less or equal to 35 days (i.e next opex)
                if ($expirationDate->isYesterday() === false && $expirationDate->diffInDays(
                        Carbon::now()
                    ) <= 35) {
                    //Get list of contract in db for expiry date
                    $contractListInDB = $this->optionsContractsRepository->getBySymbolAndExpiry(
                        $this->symbol->name,
                        $expirationDate
                    );

                    Log::debug('Contract list in db', [
                        'symbol' => $this->symbol->name,
                        'expiry' => $expirationDate->format('Y-m-d'),
                        'contracts_count' => $contractListInDB->count()
                    ]);

                    //Get range of strikes to add

                    //Insert calls, then put contracts
                    foreach ([OptionsContractsTypeEnum::CALL, OptionsContractsTypeEnum::PUT] as $contractType) {
                        Log::debug('Fetching contracts from IB', [
                            'symbol' => $this->symbol->name,
                            'expiry' => $expirationDate->format('Y-m-d'),
                            'contract_type' => $contractType
                        ]);

                        //Get contracts from IB
                        $contractsListFromIBGateway = $this->ibGatewayService->getOptionsContracts(
                            $this->symbol->name,
                            $expirationDate,
                            $contractType
                        );

                        Log::debug('Contracts fetched from IB', [
                            'symbol' => $this->symbol->name,
                            'expiry' => $expirationDate->format('Y-m-d'),
                            'contract_type' => $contractType,
                            'contracts_response' => $contractsListFromIBGateway
                        ]);

                        //Fetch strikes from db to avoid duplicates
                        $contractIdsAlreadyInDatabase = $contractListInDB->where(
                            'option_type',
                            '=',
                            $contractType
                        )->pluck('contract_id')->toArray();

                        if(Arr::get($contractsListFromIBGateway, 'status', 200) !== 422) {
                            //Filter by allowed range as per our configuration
                            $filteredContractsListFromIB = array_filter(
                                $contractsListFromIBGateway,
                                function ($contractRow) use ($contractIdsAlreadyInDatabase) {
                                    return !in_array(
                                        $contractRow['contract']['conId'],
                                        $contractIdsAlreadyInDatabase
                                    );
                                }
                            );

                            $tradingHoursList = $this->getTradingHours(Arr::first($filteredContractsListFromIB));

                            if(empty($tradingHoursList)) {
                                Log::info('No trading hours found for symbol. Contract Expired.', [
                                    'symbol' => $this->symbol->name,
                                    'expiry' => $expirationDate->format('Y-m-d'),
                                    'contract_type' => $contractType
                                ]);
                            } else {
                                Log::info('Contracts fetched from IB', [
                                    'symbol' => $this->symbol->name,
                                    'expiry' => $expirationDate->format('Y-m-d'),
                                    'contract_type' => $contractType,
                                    'contracts_count' => count($filteredContractsListFromIB)
                                ]);

                                //Save contracts to db
                                $this->optionsContractsRepository->insertContracts(
                                    $this->symbol->name === 'SPX' ? '^SPX' : $this->symbol->name, //Format SPX name properly
                                    $expirationDate,
                                    $contractType,
                                    $filteredContractsListFromIB,
                                    $tradingHoursList
                                );
                            }
                        } else {
                            Log::warning('No contracts found for symbol after filter', [
                                'symbol' => $this->symbol->name,
                                'expiry' => $expirationDate->format('Y-m-d'),
                                'contract_type' => $contractType
                            ]);
                        }
                    }
                } else {
                    Log::debug('Skipping contract chain for symbol', [
                        'symbol' => $this->symbol->name,
                        'expiry' => $expirationDate->format('Y-m-d')
                    ]);
                }
            }
        } else {
            Log::warning('No contract chain found for symbol', [
                'symbol' => $this->symbol->name
            ]);
        }
    }

    private function fetchExpiries(): array
    {
        //Fetch contract until valid one is found
        //This no longer works.
        /*$contracts = $this->ibGatewayService->getSymbolContractChain(
            $this->symbol->name,
            $this->symbol->ib_contract_id
        );*/

        $contracts = [
            [
                'expirations' => array_map(function(Carbon $dateRow) {
                    return $dateRow->format('Ymd');
                }, CarbonPeriod::create(
                    Carbon::now(OptionsContractsTradingHours::TIMEZONE_USA)->startOfDay(),
                    Carbon::now(OptionsContractsTradingHours::TIMEZONE_USA)->addDays(10)->endOfDay()
                )->filter(function($date) {
                    return $date->isWeekday();
                })->toArray())
            ]
        ];

        return $contracts;
    }

    /**
     * @param $contractDefinition
     * @return Collection
     */
    private function getTradingHours($contractDefinition): Collection
    {
        $parsedTradingHours = collect();
        //Trading hours is empty when contract has expired
        if(!empty($contractDefinition['tradingHours'])) {
            $tradingHours = $contractDefinition['tradingHours'];

            //Explode on ; for multiple trading schedules
            $tradingHours = explode(';', $tradingHours);


            //Return a collection of trading hours rows
            foreach($tradingHours as $tradingHour) {
                //Skip schedule which is marked as closed.
                if(str_contains($tradingHour, 'CLOSED') === false) {
                    $tradingHoursDefinition = $this->tradingHoursService->parseTradingHourString(
                        $tradingHour,
                        $contractDefinition['timeZoneId']
                    );

                    $parsedTradingHours->add($this->optionsContractsTradingHoursRepository->firstOrCreateTradingHours(
                        $tradingHoursDefinition['start'],
                        $tradingHoursDefinition['end']
                    ));
                }
            }
        }

        return $parsedTradingHours;
    }

    private function initializeServices()
    {
        //Initialize services
        $this->ibGatewayService = app(IBGatewayService::class);
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->optionsContractsTradingHoursRepository = app(OptionsContractsTradingHoursRepository::class);
        $this->stockService = app(StockService::class);
        $this->tradingHoursService = app(TradingHoursService::class);
        $this->holidayRepository = app(HolidayRepository::class);
    }
}
