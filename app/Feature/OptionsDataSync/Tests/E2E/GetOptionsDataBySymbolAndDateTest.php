<?php

namespace App\Feature\OptionsDataSync\Tests\E2E;

use App\Models\OptionsLiveData;
use Carbon\Carbon;
use Tests\TestCase;

class GetOptionsDataBySymbolAndDateTest extends TestCase
{
    public function testGetOptionsDataBySymbolAndDate(): void
    {
        Carbon::setTestNow(now());

        $optionsLiveData = OptionsLiveData::factory()
            ->count(fake()->numberBetween(5, 10))
            ->sequence(fn ($sequence) => ['strike' => 300 + $sequence->index])
            ->create([
                'symbol' => 'SPY'
            ]);

        $sortedLiveOptionsData = $optionsLiveData->sortBy('expiry_date');

        $response = $this->getJson(
            route('get.options.live-data', ['symbol' => 'SPY']) . '?' . http_build_query([
                'from_date' => $sortedLiveOptionsData->first()->expiry_date->format('Y-m-d'),
                'to_date' => $sortedLiveOptionsData->last()->expiry_date->format('Y-m-d'),
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonCount($sortedLiveOptionsData->count());
    }
}
