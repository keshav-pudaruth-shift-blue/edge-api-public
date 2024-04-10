<?php

namespace Tests\Integration;

use App\Services\WsjAPI;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class WsjAPITest extends TestCase
{
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        $this->wsjAPIService = app(WsjAPI::class);
    }

    public function test_get_delayed_price_will_return_price_more_than_zero()
    {
        $symbol = $this->faker->randomElement([
            'SPX',
            '^SPX',
            'SPY',
            'QQQ',
            'ES',
        ]);

        $price = $this->wsjAPIService->getDelayedCurrentPrice($symbol);

        $this->assertGreaterThan(0, $price);
    }

}
