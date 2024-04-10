<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BenzingaService
{
    /**
     * @var \Illuminate\Http\Client\PendingRequest
     */
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => config('ib-gateway.base_uri').'og/benzinga/',
            'timeout' => 60,
            'connect_timeout' => config('ib-gateway.connect_timeout'),
            'http_errors' => false
        ]);
    }

    public function getAnalystRatings(): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->retry(3,3000)->get("analyst-ratings");
    }
}
