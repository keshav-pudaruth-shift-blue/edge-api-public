<?php

namespace App\Feature\Discord\Tests\Integration;

use App\Feature\CuriousSignals\Models\CuriousSignals;
use App\Feature\Discord\Services\DiscordAlertService;
use App\Models\OptionsContracts;
use Tests\TestCase;

class DiscordAlertServiceTest extends TestCase
{
    /**
     * @var DiscordAlertService
     */
    private $discordAlertService;

    public function setUp(): void
    {
        parent::setUp();

        $this->discordAlertService = app(DiscordAlertService::class);
    }

    public function test_bullish_call_alert_must_send_alert()
    {
        $optionContract = OptionsContracts::factory()->create([
            'symbol' => '^SPX',
            'strike_price' => 4150,
            'option_type' => 'C',
            'expiry_date' => now()->toDateString(),
        ]);

        $data = [
            'open' => 0.05,
            'high' => 0.10,
            'low' => 0.05,
            'close' => 0.10,
            'volume' => 150,
            'WAP' => 0.10,
        ];

        $response = $this->discordAlertService->sendAlert($optionContract, CuriousSignals::SIGNAL_HIGH_VOLUME, $data);

        $responseJson = $response->json();

        $this->assertTrue($response->successful());
    }

    public function test_bearish_call_alert_must_send_alert()
    {
        $optionContract = OptionsContracts::factory()->create([
            'symbol' => '^SPX',
            'strike_price' => 4150,
            'option_type' => 'C',
            'expiry_date' => now()->toDateString(),
        ]);

        $data = [
            'open' => 0.10,
            'high' => 0.10,
            'low' => 0.05,
            'close' => 0.05,
            'volume' => 150,
            'WAP' => 0.10,
        ];

        $response = $this->discordAlertService->sendAlert($optionContract, CuriousSignals::SIGNAL_UNUSUAL_ACTIVITY, $data);

        $responseJson = $response->json();

        $this->assertTrue($response->successful());
    }

    public function test_bullish_put_alert_must_send_alert()
    {
        $optionContract = OptionsContracts::factory()->create([
            'symbol' => '^SPX',
            'strike_price' => 4150,
            'option_type' => 'P',
            'expiry_date' => now()->toDateString(),
        ]);

        $data = [
            'open' => 0.10,
            'high' => 0.20,
            'low' => 0.05,
            'close' => 0.20,
            'volume' => 150,
            'WAP' => 0.10,
        ];

        $response = $this->discordAlertService->sendAlert($optionContract, $data);

        $responseJson = $response->json();

        $this->assertTrue($response->successful());
    }

    public function test_bearish_put_alert_must_send_alert()
    {
        $optionContract = OptionsContracts::factory()->create([
            'symbol' => '^SPX',
            'strike_price' => 4150,
            'option_type' => 'P',
            'expiry_date' => now()->toDateString(),
        ]);

        $data = [
            'open' => 0.20,
            'high' => 0.20,
            'low' => 0.05,
            'close' => 0.05,
            'volume' => 150,
            'WAP' => 0.10,
        ];

        $response = $this->discordAlertService->sendAlert($optionContract, $data);

        $responseJson = $response->json();

        $this->assertTrue($response->successful());
    }
}
