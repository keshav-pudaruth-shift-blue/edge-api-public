<?php

namespace App\Services;

use App\Feature\PolygonIo\Services\StockAPI;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * @var StockAPI
     */
    private $stockAPI;

    public function __construct()
    {
        $this->stockAPI = app(StockAPI::class);
    }

    /**
     * @param $symbol
     * @return float
     */
    public function getPreviousClose($symbol): float
    {
        $stockResponse = $this->stockAPI->getPrevClose($symbol);
        Log::debug('StockService::getPreviousClose', [
            'symbol' => $symbol,
            'stockResponse' => $stockResponse,
        ]);
        if(Arr::get($stockResponse,'resultsCount', 0) > 0) {
            return Arr::get($stockResponse,'results.0.c', 0);
        }

        return 0;
    }
}
