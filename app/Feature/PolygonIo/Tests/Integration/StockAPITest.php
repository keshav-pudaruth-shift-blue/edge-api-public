<?php

namespace App\Feature\PolygonIo\Tests\Integration;

use App\Feature\PolygonIo\Services\StockAPI;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockAPITest extends TestCase
{
    public function test_get_prev_close_with_successful_response_will_return_previous_close()
    {
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
                        'c' => 100,
                        'h' => 100,
                        'l' => 100,
                        't' => 100,
                        'n' => 100,
                    ]
                ]
            ], 200)
        ]);

        $response = app(StockAPI::class)->getPrevClose('AAPL');
        $this->assertArrayHasKey('ticker', $response);
        $this->assertArrayHasKey('results', $response);
        $this->assertEquals('AAPL', $response['ticker']);
        $this->assertEquals('OK', $response['status']);
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

        $response = app(StockAPI::class)->getPrevClose('XXXX');
        $this->assertArrayHasKey('ticker', $response);
        $this->assertArrayNotHasKey('results', $response);
        $this->assertEquals('OK', $response['status']);
        $this->assertEquals('0', $response['queryCount']);
    }
}
