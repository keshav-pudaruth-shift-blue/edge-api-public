<?php

namespace App\Feature\QuoteAnalyst\Jobs;

use App\Feature\ThetaData\Services\ThetaDataAPI;
use App\Feature\ThetaData\Services\ThetaDataOptionAPI;
use App\Job\BasicJob;
use App\Models\OptionsContractsTypeEnum;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class AnalyseStockQuoteWithOptionsJob extends BasicJob implements ArtisanDispatchable
{
    /**
     * @var ThetaDataAPI
     */
    private mixed $thetaDataStockAPI;
    /**
     * @var ThetaDataOptionAPI
     */
    private mixed $thetaDataOptionAPI;

    public $queue = 'thetadata';

    public string $symbol;

    public string $artisanName = 'thetadata:analyze-quote';

    public function __construct(string $symbol)
    {
        $this->symbol = $symbol;
    }

    public function handle()
    {
        $this->thetaDataStockAPI = app(ThetaDataAPI::class);
        $this->thetaDataOptionAPI = app(ThetaDataOptionAPI::class);

        $quoteDate = now()->day(13);

        //Fetch stock quote
        $stockQuoteJson = Cache::remember('stock-quote-'.$this->symbol. $quoteDate->format('Ymd'), now()->addDay(), function () use ($quoteDate) {
            return $this->thetaDataStockAPI->getStockQuote($this->symbol, $quoteDate, $quoteDate, 1000)->json()['response'];
        });

        //Fetch option quote

        if (!empty($stockQuoteJson)) {
            $stockQuoteFormatted = [];
            foreach ($stockQuoteJson as $stockQuoteRow) {
                //ms_of_day = bid
                // 6 is ask

                //Skip zero stock bid
                if($stockQuoteRow[3] === 0.0) {
                    continue;
                }

                $timeQuote = (int)$stockQuoteRow[0];
                $stringStockPrice = (string)$stockQuoteRow[3];
                if (isset($stockQuoteFormatted[$stringStockPrice])) {
                    //Difference in time must be more than 1 minute
                    if ($timeQuote - last($stockQuoteFormatted[$stringStockPrice]) >= 60000) {
                        $stockQuoteFormatted[$stringStockPrice][] = $timeQuote;
                    }
                } else {
                    $stockQuoteFormatted[$stringStockPrice] = [$timeQuote];
                }
            }

            Log::info('AnalyseStockQuoteWithOptionsJob - stockQuoteFormatted total', [
                'count' => count($stockQuoteFormatted),
            ]);

            $count = 0;

            foreach($stockQuoteFormatted as $stockPrice => $timestamps)
            {
                if($stockPrice < 464.40 && $stockPrice > 464.70) {
                    //skip lag
                    continue;
                }

                Log::info("===============================================");

                $count = $count+1;
                $startTimestamp = last($timestamps);
                $timestampReverse = array_reverse($timestamps);
                $stockPrice = floatval($stockPrice);
                $roundStockPrice = (int)round($stockPrice);

                $call10bpsStrike = ($roundStockPrice + 1) * 1000;
                $put10bpsStrike = ($roundStockPrice - 1) * 1000;

                $call20bpsStrike = ($roundStockPrice + 2) * 1000;
                $put20bpsStrike = ($roundStockPrice - 2) * 1000;

                $call10bpsQuoteResponse = Cache::remember($this->symbol."-{$call10bpsStrike}c-".$quoteDate->format('Ymd'), now()->addDay(),function() use ($quoteDate, $roundStockPrice, $call10bpsStrike){
                    return $this->thetaDataOptionAPI->getQuote(
                        $this->symbol,
                        $quoteDate->format('Ymd'),
                        $call10bpsStrike,
                        OptionsContractsTypeEnum::CALL,
                        $quoteDate,
                        $quoteDate,
                        1000,
                        true
                    )->json()['response'];
                });

                $put10bpsQuoteResponse = Cache::remember($this->symbol."${put10bpsStrike}-".$quoteDate->format('Ymd'), now()->addDay(),function() use ($quoteDate, $roundStockPrice, $put10bpsStrike){
                    return $this->thetaDataOptionAPI->getQuote(
                        $this->symbol,
                        $quoteDate->format('Ymd'),
                        $put10bpsStrike,
                        OptionsContractsTypeEnum::PUT,
                        $quoteDate,
                        $quoteDate,
                        1000,
                        true
                    )->json()['response'];
                });

                $call20bpsQuoteResponse = Cache::remember($this->symbol."${call20bpsStrike}-".$quoteDate->format('Ymd'), now()->addDay(),function() use ($quoteDate, $roundStockPrice, $call20bpsStrike){
                    return $this->thetaDataOptionAPI->getQuote(
                        $this->symbol,
                        $quoteDate->format('Ymd'),
                        $call20bpsStrike,
                        OptionsContractsTypeEnum::CALL,
                        $quoteDate,
                        $quoteDate,
                        1000,
                        true
                    )->json()['response'];
                });

                $put20bpsQuoteResponse = Cache::remember($this->symbol."${put20bpsStrike}-".$quoteDate->format('Ymd'), now()->addDay(),function() use ($quoteDate, $roundStockPrice, $put20bpsStrike){
                    return $this->thetaDataOptionAPI->getQuote(
                        $this->symbol,
                        $quoteDate->format('Ymd'),
                        $put20bpsStrike,
                        OptionsContractsTypeEnum::PUT,
                        $quoteDate,
                        $quoteDate,
                        1000,
                        true
                    )->json()['response'];
                });

                $tablePresentation = [];

                foreach($timestampReverse as $timestampRow) {
                    $call10bpsQuoteSummary = $this->findAndClassifyTimestampMatch($call10bpsQuoteResponse, $timestampRow, $stockPrice, $call10bpsStrike, $quoteDate, $tablePresentation, 'call');
                    $put10bpsQuoteSummary = $this->findAndClassifyTimestampMatch($put10bpsQuoteResponse, $timestampRow, $stockPrice, $put10bpsStrike, $quoteDate, $tablePresentation, 'put');

                    $call20bpsQuoteSummary = $this->findAndClassifyTimestampMatch($call20bpsQuoteResponse, $timestampRow, $stockPrice, $call20bpsStrike, $quoteDate, $tablePresentation, 'call');
                    $put20bpsQuoteSummary = $this->findAndClassifyTimestampMatch($put20bpsQuoteResponse, $timestampRow, $stockPrice, $put20bpsStrike, $quoteDate, $tablePresentation, 'put');
                }

            }

            return false;
        }
        //Look back to each stock quote and find the exact stock price at the time
        //Compare the timestamp of the option quote based on the spread
        //Calculate the percentage between the spreads in time
    }

    /**
     * @param array $optionQuoteResponse
     * @param int $timestampRow
     * @param int $stockPrice
     * @param Carbon $quoteDate
     * @param array $tablePresentation
     * @return array
     */
    private function findAndClassifyTimestampMatch(array $optionQuoteResponse, int $timestampRow, float $stockPrice, int $strike, Carbon $quoteDate, array &$tablePresentation, $type)
    {
        $quoteRow = Arr::first($optionQuoteResponse, function($call10bpsQuoteRow, $key) use($timestampRow) {
            return $call10bpsQuoteRow[0] === $timestampRow;
        });

        $bid = $quoteRow[3];
        $ask = $quoteRow[7];

        $contractName = $strike.' '.$type;

        //If the call10bpsStartBid is not empty and the bid is more than zero
        if(!empty($quoteRow) && $quoteRow[3] > 0.0) {
            $spreadChange = 0;
            if(!empty($tablePresentation[$contractName])) {
                $spreadChange = round(((last($tablePresentation[$contractName])[2] - $bid) / $bid) * 100);

                if (abs($spreadChange) > 5.0) {
                    Log::info('AnalyseStockQuoteWithOptionsJob -  Significant spread found', [
                        'contract' => $contractName,
                        'stockPrice' => $stockPrice,
                        'currentDatetime' => $quoteDate->startOfDay()->addMilliseconds($timestampRow)->toDateTimeString(),
                        'nextDatetime' => $quoteDate->startOfDay()->addMilliseconds(last($tablePresentation[$contractName])[1])->toDateTimeString(),
                        'currentBid' => $bid,
                        'nextBid' => last($tablePresentation[$contractName])[2],
                        'spreadChange' => $spreadChange . '%'
                    ]);
                } else {
                    Log::info('AnalyseStockQuoteWithOptionsJob - Nothing Spread found', [
                        'contract' => $contractName,
                        'stockPrice' => $stockPrice,
                        'currentDatetime' => $quoteDate->startOfDay()->addMilliseconds($timestampRow)->toDateTimeString(),
                        'nextDatetime' => $quoteDate->startOfDay()->addMilliseconds(last($tablePresentation[$contractName])[1])->toDateTimeString(),
                        'currentBid' => $bid,
                        'nextBid' => last($tablePresentation[$contractName])[2],
                        'spreadChange' => $spreadChange . '%'
                    ]);
                }
            }
            $quoteSummary = [
                $stockPrice,
                $timestampRow,
                $bid,
                $spreadChange
            ];

            $tablePresentation[$contractName][] = $quoteSummary;

            return $quoteSummary;

        } else {
            Log::warning('AnalyseStockQuoteWithOptionsJob - call10bpsStartBid not found or bid is zero', [
                'stockPrice' => $stockPrice,
                'contractName' => $contractName,
                'timestampRow' => $timestampRow,
                'option' => $type,
                'bid' => $bid
            ]);

            return [];
        }
    }
}
