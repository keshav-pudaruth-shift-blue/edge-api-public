<?php

namespace App\Feature\OptionsDataSync\Services;

use App\Repositories\OptionsContractsWithTradingHoursRepository;
use Illuminate\Support\Facades\Http;

class CBOEOptionGreeksService
{
    /**
     * @var \Illuminate\Http\Client\PendingRequest
     */
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => config('ib-gateway.base_uri').'og/',
            'timeout' => 60,
            'connect_timeout' => config('ib-gateway.connect_timeout'),
            'http_errors' => false
        ]);
    }

    /**
     * @param string $symbol
     * @return \Illuminate\Http\Client\Response
     */
    public function getOptionChainsWithGreeks(string $symbol): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->retry(3,1000)->get("greeks/$symbol");
    }
}
