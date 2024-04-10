<?php

namespace App\Feature\CuriousSignals\Tests\Integration;

use App\Feature\CuriousSignals\Services\CuriousSignalService;
use Carbon\Carbon;
use Tests\TestCase;

class CuriousSignalServiceTest extends TestCase
{
    /**
     * @var CuriousSignalService
     */
    private CuriousSignalService $curiosSignalService;

    public function setUp(): void
    {
        parent::setUp();

        $this->curiosSignalService = app(CuriousSignalService::class);
    }

    /**
     * @throws \Exception
     */
    public function test_handle_with_tick_data_must_generate_grubbs_test()
    {
        $fromTime = Carbon::now()->subMinutes(5)->seconds(0);
        $toTime = Carbon::now()->seconds(0);

        $tickData = $this->generateTickData($fromTime, $toTime);

        $result = $this->curiosSignalService->handle($tickData, $fromTime, $toTime);

        $this->assertArrayHasKey('grubbs', $result);
    }

    public function test_handle_with_small_tick_data_range_must_return_zero_value()
    {
        $fromTime = Carbon::now()->subMinutes(5)->seconds(0);
        $toTime = Carbon::now()->seconds(0);

        $tickData = $this->generateTickData($fromTime, $toTime, 1, 3);

        $result = $this->curiosSignalService->handle($tickData, $fromTime, $toTime);

        $this->assertEmpty(0, $result['grubbs']);
    }

    /**
     * @param Carbon $fromTime
     * @param Carbon $toTime
     * @param int $lowValueRange
     * @param int $highValueRange
     * @return array
     */
    private function generateTickData(Carbon $fromTime, Carbon $toTime, int $lowValueRange = 1, int $highValueRange = 100): array
    {
        $fromTimeTimestamp = $fromTime->getTimestamp();
        $toTimeTimestamp = $toTime->getTimestamp();

        $tickData = [];

        while($fromTimeTimestamp <= $toTimeTimestamp) {
            $tickData[] = [
                'time' => $fromTimeTimestamp,
                'high' => rand($lowValueRange, $highValueRange),
                'low' => rand($lowValueRange, $highValueRange),
                'close' => rand($lowValueRange, $highValueRange),
                'volume' => rand($lowValueRange, $highValueRange),
                'count' => rand($lowValueRange, $highValueRange),
            ];

            //Add 5 second interval data
            $fromTimeTimestamp += 5;
        }

        return $tickData;
    }
}
