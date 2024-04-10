<?php

namespace App\Services;

use App\Feature\IBOptionsDataSync\Services\TradingHoursService;
use App\Models\SymbolList;
use Illuminate\Support\Facades\Log;

class SymbolService
{
    private TradingHoursService $tradingHoursService;

    private WsjAPI $wsjAPIService;

    public function __construct()
    {
        $this->tradingHoursService = app(TradingHoursService::class);
        $this->wsjAPIService = app(WsjAPI::class);
    }

    /**
     * @param SymbolList $symbol
     * @return array[]
     * @throws \Exception
     */
    public function calculateStrikeRange(SymbolList $symbol): array
    {
        if($this->tradingHoursService->isRegularTradingHours()) {
            //commented since we are being rate limited
            //(int)$underlyingPrice = $this->ibGatewayService->getSymbolUnderlyingPrice($symbol->name, $symbol->ib_contract_id);
            (int)$underlyingPrice = $this->wsjAPIService->getDelayedCurrentPrice($symbol->name);
        } elseif ($symbol->name === 'SPX') {
            //We don't have real time SPX data. Let's derive it from ES
            (int)$underlyingPrice = $this->wsjAPIService->getDelayedCurrentPrice("ES") - 25;
        } else {
            (int)$underlyingPrice = $this->wsjAPIService->getDelayedCurrentPrice($symbol->name);
        }

        $dte0Range = $this->calculateStrikeRangeByDTE($symbol, $underlyingPrice, 0);
        $dte1Range = $this->calculateStrikeRangeByDTE($symbol, $underlyingPrice, 1);

        //Calculate DTE strike range
        Log::debug("DTE strike range before filtering", [
            'symbol' => $symbol->name,
            '0dte_range' => $dte0Range,
            '1dte_range' => $dte1Range,
            'underlying_price' => $underlyingPrice,
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
                'C' => array_filter($dte1Range, function($strikePrice) use ($underlyingPrice, $symbol) {
                    if($symbol->name === 'SPX') {
                        return $strikePrice > ($underlyingPrice + 50); //Provide a gap on 1 dte
                    } else {
                        return $strikePrice > $underlyingPrice;
                    }
                }),
                'P' => array_filter($dte1Range, function($strikePrice) use ($underlyingPrice, $symbol) {
                    if($symbol->name === 'SPX') {
                        return $strikePrice < ($underlyingPrice - 50); //Provide a gap on 1 dte
                    } else {
                        return $strikePrice < $underlyingPrice;
                    }
                }),
            ],
        ];

        Log::debug("DTE strike range after filtering", [
            'symbol' => $symbol->name,
            'dte_range' => $finalRange,
        ]);

        return $finalRange;
    }

    private function calculateStrikeRangeByDTE(SymbolList $symbol, int $underlyingPrice, int $dte): array
    {
        if ($symbol->name === 'SPX') {
            //Return all
            return array_filter(
                range(
                    $underlyingPrice - ($dte === 0 ? $symbol->options_update_strike_range_0_dte : $symbol->options_update_strike_range_1_dte),
                    $underlyingPrice + ($dte === 0 ? $symbol->options_update_strike_range_0_dte : $symbol->options_update_strike_range_1_dte),
                )
                , function ($strikePrice) {
                return $strikePrice % 5 === 0;
            });
        } else {
            //Normal symbols
            switch($dte) {
                case 0:
                    $range = range(
                        $underlyingPrice -  $symbol->options_update_strike_range_0_dte,
                        $underlyingPrice + $symbol->options_update_strike_range_0_dte,
                    );
                    return $range;
                case 1:
                default:
                    $range = range(
                        $underlyingPrice -  $symbol->options_update_strike_range_1_dte,
                        $underlyingPrice + $symbol->options_update_strike_range_1_dte,
                    );
                    return array_filter($range, function($strikePrice) {
                        return $strikePrice % 5 === 0;
                    });
            }
        }
    }
}
