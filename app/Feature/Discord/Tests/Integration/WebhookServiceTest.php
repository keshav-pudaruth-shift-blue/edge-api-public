<?php

namespace App\Feature\Discord\Tests\Integration;

use App\Feature\Discord\Services\WebhookService;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    /**
     * @var WebhookService
     */
    private $webhookService;

    public function setUp(): void
    {
        parent::setUp();

        $this->webhookService = app(WebhookService::class);
    }

    public function test_send_must_post_webhook()
    {
        $response = $this->webhookService->send('SPX', [
            'embeds' => [
                [
                    'title' => ':green_circle: 4150C 04/06 0dte BUY',
                    'description' => $this->faker->randomElement([':boom: Volume Spike', ':rotating_light: Contrarian Trade', ':neutral_face: Neutral', ':sleepy: Awakening Volume']),
                    'fields' => [
                        [
                            'name' => 'Size',
                            'value' => '1200',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Price',
                            'value' => '$0.50',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Premium Paid',
                            'value' => '$6000',
                        ],
                        [
                            'name' => 'Average volume',
                            'value' => '50',
                        ],
                        [
                            'name' => 'Underlying price',
                            'value' => '4120.0',
                        ],
                    ]
                ],
            ]
        ]);

        $this->assertTrue($response->successful());
    }
}
