<?php

namespace App\Feature\ThetaData\Services;

use App\Models\OptionsContractsTypeEnum;
use Carbon\Carbon;
use phpDocumentor\Reflection\Types\Boolean;

class ThetaDataOptionAPI extends ThetaDataAPI
{
    /**
     * @param string $symbol
     * @param string $expirationDate
     * @param string $strike
     * @param OptionsContractsTypeEnum $right
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $interval
     * @param bool $RTH
     * @return \Illuminate\Http\Client\Response
     */
    public function getQuote(
        string $symbol,
        string $expirationDate,
        string $strike,
        OptionsContractsTypeEnum $right,
        Carbon $startDate,
        Carbon $endDate,
        int $interval=60000,
        bool $RTH=false): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->get('/v2/hist/option/quote', [
            'root' => $symbol,
            'exp' => $expirationDate,
            'strike' => $strike,
            'right' => $right->value,
            'start_date' => $startDate->format('Ymd'),
            'end_date' => $endDate->format('Ymd'),
            'ivl' => $interval,
        ]);
    }

    /**
     * @param string $symbol
     * @param Carbon $expirationDate
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return \Illuminate\Http\Client\Response
     */
    public function getOpenInterestAll(
        string $symbol,
        Carbon $expirationDate,
        Carbon $startDate,
        Carbon $endDate): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->get('/v2/bulk_hist/option/open_interest', [
            'root' => $symbol,
            'exp' => $expirationDate->format('Ymd'),
            'start_date' => $startDate->format('Ymd'),
            'end_date' => $endDate->format('Ymd'),
        ]);
    }

    /**
     * @param string $symbol
     * @param Carbon $expirationDate
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return \Illuminate\Http\Client\Response
     */
    public function getTradeQuoteAll(
        string $symbol,
        Carbon $expirationDate,
        Carbon $startDate,
        Carbon $endDate): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->timeout(120)->get('/v2/bulk_hist/option/trade_quote', [
            'root' => $symbol,
            'exp' => $expirationDate->format('Ymd'),
            'start_date' => $startDate->format('Ymd'),
            'end_date' => $endDate->format('Ymd'),
        ]);
    }

    public function getQuoteAll(
        string $symbol,
        Carbon $expirationDate,
        Carbon $startDate,
        Carbon $endDate,
        int $interval = 1000): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->get('/v2/bulk_hist/option/quote', [
            'root' => $symbol,
            'exp' => $expirationDate->format('Ymd'),
            'start_date' => $startDate->format('Ymd'),
            'end_date' => $endDate->format('Ymd'),
            'ivl' => $interval,
        ]);
    }
}
