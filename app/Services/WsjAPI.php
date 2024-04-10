<?php

namespace App\Services;


use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WsjAPI
{
    public function __construct()
    {
        $this->baseURL = 'https://api.wsj.net/api/dylan/';
        $this->httpClient = Http::withOptions([
            'base_uri' => $this->baseURL,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Origin' => 'https://www.marketwatch.com',
            ],
            'http_errors' => true,
            'timeout' => 15,
            'connect_timeout' => 5,
        ]);
    }

    public function getDelayedCurrentPrice($symbol): float
    {
        switch($symbol) {
            case '^SPX':
            case 'SPX':
                $wsjId = 'Index-US-SPX';
                break;
            case 'ES':
                $wsjId = 'Future-US-ES00';
                break;
            case 'QQQ':
                $wsjId = 'FUND-US-QQQ';
                break;
            case 'SPY':
                $wsjId = 'FUND-US-SPY';
                break;
            default:
                $wsjId = 'Stock-US-'.$symbol;
        }

        $response = $this->httpClient->retry(5, 1000)->get('quotes/v2/comp/quoteByDialect', [
                'dialect' => 'official',
                'dialects' => 'Charting',
                'needed' => 'CompositeTrading|BluegrassChannels',
                'MaxInstrumentMatches' => 1,
                'accept' => 'application/json',
                'EntitlementToken' => config('wsj-api.entitlementToken'),
                'ckey' => config('wsj-api.ckey'),
                'id' => $wsjId
        ]);

        $responseJson = $response->json();
        $price = Arr::get($responseJson, 'InstrumentResponses.0.Matches.0.CompositeTrading.Last.Price.Value', 0);
        if($price === 0) {
            Log::warning('WsjAPI::getDelayedCurrentPrice - Unable to fetch underlying price', [
                'symbol' => $symbol,
                'wsjId' => $wsjId,
                'response' => $responseJson,
            ]);
            throw new \Exception('Price not found');
        }

        return $price;
    }
}
