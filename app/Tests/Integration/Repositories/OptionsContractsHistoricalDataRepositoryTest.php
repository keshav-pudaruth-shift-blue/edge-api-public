<?php

namespace App\Tests\Integration\Repositories;

use App\Repositories\OptionsContractsHistoricalDataRepository;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OptionsContractsHistoricalDataRepositoryTest extends TestCase
{
    use WithFaker;

    /**
     * @var OptionsContractsHistoricalDataRepository
     */
    private $optionsContractsHistoricalDataRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->optionsContractsHistoricalDataRepository = $this->app->make(OptionsContractsHistoricalDataRepository::class);
    }

    public function test_check_liveness_must_return_true_if_below_threshold()
    {
        $contractData = [
            'ticks' => [
                [
                    'time' => now()->subSeconds(55)->timestamp,
                    'open' => 1,
                    'high' => 1,
                    'low' => 1,
                    'close' => 1,
                    'volume' => 1,
                ]
            ]
        ];

        $contractId = $this->faker->randomDigitNotNull;

        Cache::put('ib-historical-data:' . $contractId, $contractData);

        $this->assertTrue($this->optionsContractsHistoricalDataRepository->checkLivenessRealTimeDataByContractId($contractId));
    }
}
