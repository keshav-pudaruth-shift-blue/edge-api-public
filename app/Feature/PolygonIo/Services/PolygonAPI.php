<?php

namespace App\Feature\PolygonIo\Services;

use Illuminate\Support\Facades\Http;

class PolygonAPI
{
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => config('polygon-io.base_uri'),
            'timeout' => config('polygon-io.timeout'),
            'connect_timeout' => config('polygon-io.connect_timeout'),
            'http_errors' => false
        ]);
        $this->httpClient->withToken(config('polygon-io.api_key'));
    }

    /**
     * @return array|mixed
     */
    public function getMarketStatus()
    {
        $response = $this->httpClient->get('v1/marketstatus/now');

        return $response->json();
    }
}
