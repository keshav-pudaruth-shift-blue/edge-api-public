<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StatisticsService
{
    public function __construct()
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => env('IB_GATEWAY_BASE_URI').'stats/'
        ]);
    }

    /**
     * @param array $data
     * @return array|mixed
     */
    public function getShapiroWilk(array $data): mixed
    {
        $response = $this->httpClient->post('shapiro-wilk', [
            'data' => $data
        ]);

        return $response->json();
    }
}
