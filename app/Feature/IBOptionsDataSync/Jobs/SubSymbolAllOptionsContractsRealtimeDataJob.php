<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Feature\IBOptionsDataSync\Services\TradingHoursService;
use App\Job\BasicJob;
use App\Models\OptionsContracts;
use App\Repositories\OptionsContractsHistoricalDataRepository;
use App\Repositories\OptionsContractsRepository;
use App\Repositories\SymbolListRepository;
use App\Services\StockService;
use App\Services\SymbolService;
use App\Services\WsjAPI;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SubSymbolAllOptionsContractsRealtimeDataJob extends BasicJob implements ArtisanDispatchable, ShouldBeUnique
{
    public int $tries = 3;

    public int $retryAfter = 61;

    /**
     * @var OptionsContractsRepository
     */
    private $optionsContractsRepository;

    /**
     * @var TradingHoursService
     */
    private $tradingHoursService;

    /**
     * @var StockService
     */
    private $stockService;

    /**
     * @var SymbolListRepository
     */
    private $symbolListRepository;

    /**
     * @var SymbolService
     */
    private $symbolService;

    /**
     * @var IBGatewayService
     */
    private $ibGatewayService;

    /**
     * @var OptionsContractsHistoricalDataRepository
     */
    private $optionsContractsHistoricalDataRepository;

    /**
     * @var WsjAPI
     */
    private $wsjAPIService;

    /**
     * @throws \Exception
     */
    public function handle(): bool
    {
        $this->initializeServices();

        $optionContracts = $this->optionsContractsRepository->queryOptionContractsWithinTradingHours()
                            ->where('expiry_date','>=', now()->toDateString())
                            ->where('expiry_date','<=', now()->addDays(1)->toDateString()) //Include 1 dte
                            ->get();

        Log::debug("SubSymbolAllOptionsContractsRealtimeDataJob:: Found ".count($optionContracts)." contracts to sync");

        //Group by symbol
        $optionContracts = $optionContracts->groupBy('symbol');
        $timeDelay = 0;
        foreach ($optionContracts as $optionContractSymbol => $optionContractList) {
            $symbol = $this->symbolListRepository->getBySymbol($optionContractSymbol);
            if(empty($symbol) || $symbol->sync_options_historical_data_live_0_dte_enabled === false) {
                Log::info("SubSymbolAllOptionsContractsRealtimeDataJob:: Skipping symbol $optionContractSymbol - 0 dte not enabled");
                continue;
            }
            $dteRange = $this->symbolService->calculateStrikeRange($symbol);
            foreach ($optionContractList as $optionContract) {
                $expirationOrder = $this->determineExpirationOrder($optionContract);

                Log::debug("SubSymbolAllOptionsContractsRealtimeDataJob - Syncing historical data for contract id", [
                    'contract_id' => $optionContract->contract_id,
                    'symbol' => $optionContract->symbol,
                    'dte' => $optionContract->dte,
                    'expiration_order' => $expirationOrder,
                    'expiry_date' => $optionContract->expiry_date,
                    'strike' => $optionContract->strike_price,
                    'option_type' => $optionContract->option_type,
                ]);

                switch ($expirationOrder) {
                    //0 dte only for now
                    case 1:
                        if (in_array(
                                $optionContract->strike_price,
                                $dteRange[$expirationOrder][$optionContract->option_type]
                            ) === true ) {
                            if($this->optionsContractsHistoricalDataRepository->checkLivenessRealTimeDataByContractId($optionContract->contract_id) === false) {
                                Log::debug("SubSymbolAllOptionsContractsRealtimeDataJob - Contract sync started", [
                                    'contract_id' => $optionContract->contract_id,
                                    'symbol' => $optionContract->symbol,
                                    'dte' => $optionContract->dte,
                                    'expiration_order' => $expirationOrder,
                                    'expiry_date' => $optionContract->expiry_date,
                                    'strike' => $optionContract->strike_price,
                                    'option_type' => $optionContract->option_type,
                                ]);
                                //Lock for 10 mins
                                $contractRealTimeLock = Cache::lock('ibgateway:contract-realtime-sub:' . $optionContract->contract_id, 600);
                                if($contractRealTimeLock->get()) {
                                    dispatch(new SubSymbolOptionsContractsRealtimeDataJob($optionContract->contract_id, $symbol))->delay(now()->addSeconds($timeDelay));
                                    $timeDelay = $timeDelay + 15;
                                } else {
                                    Log::debug("SubSymbolAllOptionsContractsRealtimeDataJob - Contract sync skipped due to time lock", [
                                        'contract_id' => $optionContract->contract_id,
                                        'symbol' => $optionContract->symbol,
                                        'dte' => $optionContract->dte,
                                        'expiration_order' => $expirationOrder,
                                        'expiry_date' => $optionContract->expiry_date,
                                        'strike' => $optionContract->strike_price,
                                        'option_type' => $optionContract->option_type,
                                    ]);
                                }
                            } else {
                                Log::debug("SubSymbolAllOptionsContractsRealtimeDataJob - Contract sync skipped due to liveness", [
                                    'contract_id' => $optionContract->contract_id,
                                    'symbol' => $optionContract->symbol,
                                    'dte' => $optionContract->dte,
                                    'expiration_order' => $expirationOrder,
                                    'expiry_date' => $optionContract->expiry_date,
                                    'strike' => $optionContract->strike_price,
                                    'option_type' => $optionContract->option_type,
                                ]);
                            }
                        } else {
                            Log::debug("SubSymbolAllOptionsContractsRealtimeDataJob - Contract sync skipped due to out of range", [
                                'contract_id' => $optionContract->contract_id,
                                'symbol' => $optionContract->symbol,
                                'dte' => $optionContract->dte,
                                'expiration_order' => $expirationOrder,
                                'expiry_date' => $optionContract->expiry_date,
                                'strike' => $optionContract->strike_price,
                                'option_type' => $optionContract->option_type,
                            ]);
                        }
                        break;
                    default:
                        //Skip the rest
                        Log::debug("SubSymbolAllOptionsContractsRealtimeDataJob - Contract sync skipped due to DTE filter", [
                            'contract_id' => $optionContract->contract_id,
                            'symbol' => $optionContract->symbol,
                            'dte' => $optionContract->dte,
                            'expiration_order' => $expirationOrder,
                            'expiry_date' => $optionContract->expiry_date,
                            'strike' => $optionContract->strike_price,
                            'option_type' => $optionContract->option_type,
                        ]);
                        break;
                }
            }
        }

        return true;
    }

    public function tags(): array
    {
        return ['sync', 'sync:options', 'sync:options:historical', 'sync:options:historical:realtime', 'sync:options:historical:realtime:all'];
    }


    /**
     * @param OptionsContracts $optionsContractRow
     * @return int
     */
    private function determineExpirationOrder(OptionsContracts $optionsContractRow): int
    {
        if ($optionsContractRow->symbol === '^SPX') {
            //Since SPX trades PM and during market hours, we need to divide by 2
            return ceil($optionsContractRow->tradingHours->where(
                    'end_datetime',
                    '>=',
                    now()->toDateTimeString()
                )->count() / 2);
        } else {
            return $optionsContractRow->tradingHours->where('end_datetime', '>=', now()->toDateTimeString())->count();
        }
    }

    private function initializeServices()
    {
        $this->ibGatewayService = app(IbGatewayService::class);
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->optionsContractsHistoricalDataRepository = app(OptionsContractsHistoricalDataRepository::class);
        $this->tradingHoursService = app(TradingHoursService::class);
        $this->stockService = app(StockService::class);
        $this->symbolListRepository = app(SymbolListRepository::class);
        $this->symbolService = app(SymbolService::class);
        $this->wsjAPIService = app(WsjAPI::class);
    }
}
