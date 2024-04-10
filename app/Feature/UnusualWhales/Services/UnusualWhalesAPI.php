<?php

namespace App\Feature\UnusualWhales\Services;

use Illuminate\Support\Facades\Http;
use \Illuminate\Http\Client\Response;

class UnusualWhalesAPI
{
    /**
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => 'https://phx.unusualwhales.com/api/'
        ])->withToken(config('services.unusualwhales.auth_token'));
    }

    /**
     * @param string $stockSymbol
     * @return Response
     * @throws \Exception
     */
    public function getOptionChainsWithGreeks(string $stockSymbol): Response
    {
        return $this->httpClient->retry(3, random_int(1, 4))->get("greeks_chains/$stockSymbol");
    }
}
