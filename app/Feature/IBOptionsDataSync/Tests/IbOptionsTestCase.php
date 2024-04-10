<?php

namespace App\Feature\IBOptionsDataSync\Tests;

use App\Feature\IBOptionsDataSync\Services\TradingHoursService;
use App\Feature\PolygonIo\Services\PolygonAPI;
use App\Models\OptionsContractsHistoricalData;
use App\Models\OptionsContractsTypeEnum;
use App\Models\SymbolList;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Tests\TestCase;

class IbOptionsTestCase extends TestCase
{
    /**
     * @param SymbolList $symbol
     * @return array[]
     */
    protected function generateOptionChainData(SymbolList $symbol): array
    {
        return [
            [
                'exchange' => 'NASDAQOM',
                'underlyingConId' => $symbol->ib_contract_id,
                'tradingClass' => $symbol->name,
                'multiplier' => '100',
                'expirations' => [
                    now()->addWeeks(1)->day(Carbon::FRIDAY)->format('Ymd'),
                    now()->addWeeks(2)->day(Carbon::FRIDAY)->format('Ymd'),
                    now()->addWeeks(3)->day(Carbon::FRIDAY)->format('Ymd'),
                ],
                'strikes' => [
                    100,
                    200,
                    300,
                ]
            ]
        ];
    }

    protected function generateOptionContractByStrike(SymbolList $symbol, $strike, Carbon $expiryDate, $type = OptionsContractsTypeEnum::CALL)
    {

    }

    protected function generateOptionContractDefinition(SymbolList $symbol, Carbon $expiryDate, $strike, $type = OptionsContractsTypeEnum::CALL): array
    {
        return [
            'contract' => [
                'symbol' => $symbol->name,
                'secType' => 'OPT',
                'strike' => $strike,
                'right' => $type,
                'multiplier' => '100',
                'exchange' => 'SMART',
                'currency' => 'USD',
                'localSymbol' => 'SPXW  230321C01200000',
                'tradingClass' => 'SPXW',
                'conId' => $this->faker->unique()->randomNumber(8,true),
                'primaryExchange' => 'CBOE',
            ],
            'marketName' => 'SPXW',
            'minTick' => '0.01',
            'orderTypes' => 'ACTIVETIM,AD,ADJUST,ALERT,ALLOC,AVGCOST,BASKET,COND,CONT,COVER,DAY,DEACT,DEACTDET,DEL,DET,DIS,DISPLACE,ENH,EX,EXCH,EXERCISE,FIXED,FIXEDPCT,FOK,GTC,GTD,GTT,HID,ICE,LIT,MID,MIT,MTL,MKT,MTCH,NEGO,NETL,OPG,PEG,PEGMID,PEGLIT,REL,REPLACE,RESERVE,RESUME,RFQ,SCALE,SNAPMID,SNAP,STP,STPLMT,STPLMTPEG,STPPEG,STPPCT,STPPCTPEG,STPRFQ,TRAIL,TRAILLIT,TRAILLIMIT,TRAILLMTPEG,TRAILMIT,TRAILMITPEG,TRAILPEG,TRAILPCT,TRAILPCTPEG,TRAILREL,TRAILRFQ,TRAILSTPLMT,TRAILSTPLMTPEG,TRAILSTPPEG,TRAILSTPPCT,TRAILSTPPCTPEG,TRAILSTPRFQ,TRAILVWAP,TRAILVWAPPEG,VWAP,VWAPPEG',
            'validExchanges' => 'SMART,CBOE,IBUSOPT',
            'priceMagnifier' => '100',
            'underConId' => $this->faker->randomNumber(8),
            'longName' => 'S&P 500 Stock Index',
            'contractMonth' => now()->format('Ym'),
            'industry' => 'Indices',
            'category' => 'Index',
            'subcategory' => 'Equity Index',
            'timeZoneId' => 'US/Central',
            'tradingHours' => now('UTC')->hour(8)->minutes(30)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA).'-'.now()->hour(16)->minutes(0)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA).';'.now('UTC')->addDay()->hour(8)->minutes(30)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA).'-'.now()->addDay()->hour(16)->minutes(0)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA),
            'liquidHours' => now('UTC')->hour(8)->minutes(30)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA).'-'.now()->hour(16)->minutes(0)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA).';'.now('UTC')->addDay()->hour(8)->minutes(30)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA).'-'.now()->addDay()->hour(16)->minutes(0)->seconds(0)->format(TradingHoursService::DATETIME_FORMAT_USA),
            'evRule' => '',
            'evMultiplier' => '0',
            'aggGroup' => '0',
            'underSymbol' => 'SPX',
            'underSecType' => 'IND',
            'marketRuleIds' => '110,110,110',
            'realExpirationDate' => $expiryDate->format('Ymd'),
        ];
    }

    protected function generateHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $currentTime = $startDate;

        while(true) {
            $data[] = [
                'time' => $currentTime->format(OptionsContractsHistoricalData::DATETIME_FORMAT_USA),
                'open' => $this->faker->randomFloat(2, 100, 200),
                'high' => $this->faker->randomFloat(2, 100, 200),
                'low' => $this->faker->randomFloat(2, 100, 200),
                'close' => $this->faker->randomFloat(2, 100, 200),
                'volume' => $this->faker->randomNumber(6),
                'count' => $this->faker->randomNumber(6),
                'WAP' => $this->faker->randomFloat(2, 100, 200),
            ];

            $currentTime->addMinute();

            if($currentTime->greaterThanOrEqualTo($endDate)) {
                break;
            }
        }

        return $data;
    }
}
