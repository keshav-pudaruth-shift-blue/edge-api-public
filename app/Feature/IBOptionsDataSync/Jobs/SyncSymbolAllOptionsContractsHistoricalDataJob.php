<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Feature\IBOptionsDataSync\Services\TradingHoursService;
use App\Job\BasicJob;
use App\Models\OptionsContracts;
use App\Models\SymbolList;
use App\Repositories\OptionsContractsRepository;
use App\Repositories\SymbolListRepository;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncSymbolAllOptionsContractsHistoricalDataJob extends BasicJob implements ArtisanDispatchable
{
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
     * @var IBGatewayService
     */
    private $ibGatewayService;

    public function handle(): bool
    {
        $this->initializeServices();

        $optionContracts = $this->optionsContractsRepository->getOptionContractsWithinTradingHours();

        //Group by symbol
        $optionContracts = $optionContracts->groupBy('symbol');
        foreach ($optionContracts as $optionContractSymbol => $optionContractList) {
            $symbol = $this->symbolListRepository->getBySymbol($optionContractSymbol);
            if(empty($symbol) || $symbol->sync_options_historical_data_delayed_enabled === false) {
                Log::info("Skipping symbol $optionContractSymbol");
                continue;
            }
            $dteRange = $this->calculateStrikeRange($symbol);
            foreach ($optionContractList as $optionContract) {
                Log::debug("Syncing historical data for contract id", [
                    'contract_id' => $optionContract->contract_id,
                    'symbol' => $optionContract->symbol,
                    'dte' => $optionContract->dte,
                    'expiry_date' => $optionContract->expiry_date,
                    'strike' => $optionContract->strike_price,
                    'option_type' => $optionContract->option_type,
                ]);

                $expirationOrder = $this->determineExpirationOrder($optionContract);

                switch ($expirationOrder) {
                    //1 dte only for now
                    case 2:
                        if (in_array(
                                $optionContract->strike_price,
                                $dteRange[$expirationOrder][$optionContract->option_type]
                            ) === true) {
                            dispatch(
                                new SyncSymbolOptionsContractsHistoricalDataJob($optionContract->contract_id)
                            );
                        } else {
                            Log::debug("Contract sync skipped", [
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
                        Log::debug("Contract sync skipped", [
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


    /**
     * @param OptionsContracts $optionsContractRow
     * @return int
     */
    private function determineExpirationOrder(OptionsContracts $optionsContractRow): int
    {
        if ($optionsContractRow->symbol === 'SPX') {
            //Since SPX trades PM and during market hours, we need to divide by 2
            return (int)$optionsContractRow->tradingHours->where(
                    'end_datetime',
                    '>=',
                    now()->toDateTimeString()
                )->count() / 2;
        } else {
            return $optionsContractRow->tradingHours->where('end_datetime', '>=', now()->toDateTimeString())->count();
        }
    }

    private function calculateStrikeRangeByDTE(SymbolList $symbol, int $underlyingPrice, int $dte): array
    {
        if ($symbol->name === '^SPX') {
            return array_filter(
                range(
                    $underlyingPrice - ($dte === 0 ? $symbol->options_update_strike_range_0_dte : $symbol->options_update_strike_range_1_dte),
                    $underlyingPrice + ($dte === 0 ? $symbol->options_update_strike_range_0_dte : $symbol->options_update_strike_range_1_dte),
                )
            , function ($strikePrice) {
                return $strikePrice % 25 === 0;
            });
        } else {
            return array_filter(
                range(
                    $underlyingPrice - ($dte === 0 ? $symbol->options_update_strike_range_0_dte : $symbol->options_update_strike_range_1_dte),
                    $underlyingPrice + ($dte === 0 ? $symbol->options_update_strike_range_0_dte : $symbol->options_update_strike_range_1_dte),
                )
            , function ($strikePrice) use ($dte) {
                return !($dte === 1) || $strikePrice % 5 === 0;
            });
        }
    }

    protected function calculateStrikeRange(SymbolList $symbol): array
    {
        if($this->tradingHoursService->isRegularTradingHours()) {
            (int)$underlyingPrice = $this->ibGatewayService->getSymbolUnderlyingPrice($symbol->name, $symbol->ib_contract_id);
        } else if ($symbol->name === '^SPX') {
            //We don't have real time SPX data but SPY. Let's fake it
            (int)$underlyingPrice = $this->stockService->getPreviousClose("SPY") * 10;
        } else {
            (int)$underlyingPrice = $this->stockService->getPreviousClose($symbol->name);
        }

        $dte0Range = $this->calculateStrikeRangeByDTE($symbol, $underlyingPrice, 0);
        $dte1Range = $this->calculateStrikeRangeByDTE($symbol, $underlyingPrice, 1);

        //Calculate DTE strike range
        Log::debug("DTE strike range before filtering", [
            'symbol' => $symbol->name,
            'dte_range' => $dte0Range,
            'dte_range_1' => $dte1Range,
        ]);

        //Select only OTM strikes for both calls and puts
        $finalRange = [
            1 => [
                 'C' => array_filter($dte0Range, function($strikePrice) use ($underlyingPrice) {
                    return $strikePrice > $underlyingPrice;
                }),
                'P' => array_filter($dte0Range, function($strikePrice) use ($underlyingPrice) {
                    return $strikePrice < $underlyingPrice;
                }),
            ],
            2 => [
                'C' => array_filter($dte1Range, function($strikePrice) use ($underlyingPrice) {
                    return $strikePrice > $underlyingPrice;
                }),
                'P' => array_filter($dte1Range, function($strikePrice) use ($underlyingPrice) {
                    return $strikePrice < $underlyingPrice;
                }),
            ]
        ];

        Log::debug("DTE strike range after filtering", [
            'symbol' => $symbol->name,
            'dte_range' => $finalRange,
        ]);

        return $finalRange;
    }

    private function initializeServices()
    {
        $this->ibGatewayService = app(IbGatewayService::class);
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->tradingHoursService = app(TradingHoursService::class);
        $this->stockService = app(StockService::class);
        $this->symbolListRepository = app(SymbolListRepository::class);
    }
}
