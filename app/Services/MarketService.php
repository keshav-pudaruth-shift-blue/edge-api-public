<?php

namespace App\Services;

use App\Feature\PolygonIo\Services\PolygonAPI;
use Illuminate\Support\Arr;

class MarketService
{
    /**
     * @var PolygonAPI
     */
    protected $polygonAPI;

    public function __construct()
    {
        $this->polygonAPI = app(PolygonAPI::class);
    }

    /**
     * @param string $exchange
     * @return bool
     */
    public function isMarketOpen(string $exchange='nasdaq'): bool
    {
        $marketStatusResponse = $this->polygonAPI->getMarketStatus();
        $marketStatus = Arr::get($marketStatusResponse,'exchanges.'.$exchange);

        if($marketStatus === 'open') {
            return true;
        } else {
            return false;
        }
    }


}
