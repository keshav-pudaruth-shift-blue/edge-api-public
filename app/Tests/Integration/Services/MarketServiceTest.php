<?php

namespace App\Tests\Integration\Services;

use App\Services\MarketService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_is_market_open_method_with_api_response_successful_market_open_will_return_true()
    {
        Http::fake([
            'https://api.polygon.io/v1/marketstatus/now' => Http::response([
                'exchanges' => [
                    'nasdaq' => 'open'
                ]
            ], 200)
        ]);

        $this->assertTrue(app(MarketService::class)->isMarketOpen());
    }

    public function test_is_market_open_method_with_api_response_successful_and_market_closed_will_return_false()
    {
        Http::fake([
            'https://api.polygon.io/v1/marketstatus/now' => Http::response([
                'exchanges' => [
                    'nasdaq' => 'closed'
                ]
            ], 200)
        ]);

        $this->assertFalse(app(MarketService::class)->isMarketOpen());
    }

    public function test_is_market_open_method_with_api_response_successful_and_market_extended_hours_will_return_false()
    {
        Http::fake([
            'https://api.polygon.io/v1/marketstatus/now' => Http::response([
                'exchanges' => [
                    'nasdaq' => 'extended-hours'
                ]
            ], 200)
        ]);

        $this->assertFalse(app(MarketService::class)->isMarketOpen());
    }
}
