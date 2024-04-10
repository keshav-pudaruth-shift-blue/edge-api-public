<?php

namespace App\Feature\UnusualWhales\Tests\E2E\Services;

use App\Feature\UnusualWhales\Services\UnusualWhalesAPI;
use Illuminate\Foundation\Testing\WithFaker;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class UnusualWhalesAPITest extends TestCase
{
    use WithFaker;

    private $unusualWhalesAPI;

    public function setUp(): void
    {
        parent::setUp();

        $this->unusualWhalesAPI = new UnusualWhalesAPI();
    }

    /**
     * @test
     */
    public function it_can_get_option_chains_with_greeks()
    {
        $response = $this->unusualWhalesAPI->getOptionChainsWithGreeks($this->faker->randomElement(['SPY','QQQ','VIX']));

        $this->assertEquals(Response::HTTP_OK, $response->status());

        $responseJson = $response->json();
        $this->assertIsArray($responseJson);
        $this->assertArrayHasKey('data',$responseJson);
        $this->assertArrayHasKey('open_interest', $responseJson['data'][0]);
        $this->assertArrayHasKey('delta', $responseJson['data'][0]);
        $this->assertArrayHasKey('gamma', $responseJson['data'][0]);
        $this->assertArrayHasKey('rho', $responseJson['data'][0]);
        $this->assertArrayHasKey('option_symbol', $responseJson['data'][0]);
        $this->assertArrayHasKey('option_type', $responseJson['data'][0]);
        $this->assertArrayHasKey('expires', $responseJson['data'][0]);
    }
}
