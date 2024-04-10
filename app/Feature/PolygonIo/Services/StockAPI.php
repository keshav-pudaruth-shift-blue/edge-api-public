<?php

namespace App\Feature\PolygonIo\Services;

class StockAPI extends PolygonAPI
{
    /**
     * @param $symbol
     * @return array|mixed
     */
    public function getPrevClose($symbol)
    {
        $response = $this->httpClient->get("v2/aggs/ticker/$symbol/prev");

        return $response->json();
    }
}
