<?php

namespace Database\Factories\InteractiveBrokers;

use App\Models\InteractiveBrokers\OptionContractDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionContractDefinition>
 */
class OptionContractDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract' => [
                'symbol' => $this->faker->randomElement(['AAPL', 'AMZN', 'GOOG', 'MSFT', 'TSLA', 'SPY', 'SPX', 'QQQ']),
                'secType' => 'OPT',
                'strike' => $this->faker->randomFloat(2, 1, 500),
                'right' => $this->faker->randomElement(['C', 'P']),
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
            'tradingHours' => now()->hour(8)->minutes(30)->format('Ymd:His').'-'.now()->hour(16)->minutes(0)->format('Ymd:His').';'.now()->addDay()->hour(8)->minutes(30)->format('Ymd:His').'-'.now()->addDay()->hour(16)->minutes(0)->format('Ymd:His'),
            'liquidHours' => now()->hour(8)->minutes(30)->format('Ymd:His').'-'.now()->hour(16)->minutes(0)->format('Ymd:His').';'.now()->addDay()->hour(8)->minutes(30)->format('Ymd:His').'-'.now()->addDay()->hour(16)->minutes(0)->format('Ymd:His'),
            'evRule' => '',
            'evMultiplier' => '0',
            'aggGroup' => '0',
            'underSymbol' => 'SPX',
            'underSecType' => 'IND',
            'marketRuleIds' => '110,110,110',
            'realExpirationDate' => now()->addDay()->format('Ymd'),
        ];
    }
}
