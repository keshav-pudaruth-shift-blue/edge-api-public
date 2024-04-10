<?php

namespace App\Tests\Integration\Services;

use App\Services\StockService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    public function test_get_prev_close_with_successful_response_will_return_previous_close()
    {
        $previousClose = $this->faker->randomFloat(2,1,100);

        Http::fake([
            'https://api.polygon.io/v2/aggs/ticker/AAPL/prev' => Http::response([
                'ticker' => 'AAPL',
                'status' => 'OK',
                'queryCount' => 1,
                'resultsCount' => 1,
                'adjusted' => true,
                'results' => [
                    [
                        'v' => 100,
                        'vw' => 100,
                        'o' => 100,
                        'c' => $previousClose,
                        'h' => 100,
                        'l' => 100,
                        't' => 100,
                        'n' => 100,
                    ]
                ]
            ], 200)
        ]);

        $previousCloseResponse = app(StockService::class)->getPreviousClose('AAPL');

        $this->assertEquals($previousClose, $previousCloseResponse);

    }

    public function test_get_prev_close_with_inexistent_symbol_will_return_no_results()
    {
        Http::fake([
            'https://api.polygon.io/v2/aggs/ticker/AAPL/prev' =>
                Http::response([
                    'ticker' => 'XXXX',
                    'status' => 'OK',
                    'queryCount' => 0,
                    'resultsCount' => 0,
                    'adjusted' => true
                ], 200)
        ]);

        $previousCloseResponse = app(StockService::class)->getPreviousClose('XXXX');

        $this->assertEquals(0, $previousCloseResponse);
    }
}
