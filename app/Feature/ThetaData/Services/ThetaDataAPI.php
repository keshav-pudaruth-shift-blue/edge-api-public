<?php

namespace App\Feature\ThetaData\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ThetaDataAPI
{
    protected \Illuminate\Http\Client\PendingRequest $httpClient;

    public function __construct()
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => config('theta-data.base_uri'),
            'timeout' => config('theta-data.timeout'),
            'connect_timeout' => config('theta-data.connect_timeout'),
            'http_errors' => false,
            'verify' => false,
        ]);
    }

    public function getExpirations(string $symbol): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->get('/v2/list/expirations', [
            'root' => $symbol
        ]);
    }

    public function getStrikes(string $symbol, Carbon $expirationDate): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->get('/v2/list/strikes', [
            'root' => $symbol,
            'exp' => $expirationDate->format('Ymd')
        ]);
    }

    public function getStockEOD(string $symbol, Carbon $startDate, Carbon $endDate): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->get('/hist/stock/eod', [
            'root' => $symbol,
            'start_date' => $startDate->format('Ymd'),
            'end_date' => $endDate->format('Ymd')
        ]);
    }

    public function getStockLastEODClose(string $symbol): float
    {
        if(Cache::has("stock_last_close_$symbol")) {
            return Cache::get("stock_last_close_$symbol");
        }

        $response = $this->getStockEOD($symbol, now()->subWeek(), now()->addDay());

        if($response->successful()) {
            $data = $response->json();
            $lastCloseRow = last($data['response']);
            if (Arr::has($lastCloseRow, 3)) {
                //Cache for 1 hour
                Cache::put("stock_last_close_$symbol", $lastCloseRow[3], now()->addHours(23));
                return $lastCloseRow[3];
            }
        }

        return 0.0;
    }

    /**
     * @param string $symbol
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $interval
     * @return \Illuminate\Http\Client\Response
     */
    public function getStockQuote(string $symbol, Carbon $startDate, Carbon $endDate, int $interval=1000): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->get('/hist/stock/quote', [
            'root' => $symbol,
            'start_date' => $startDate->format('Ymd'),
            'end_date' => $endDate->format('Ymd'),
            'ivl' => $interval
        ]);
    }
}
