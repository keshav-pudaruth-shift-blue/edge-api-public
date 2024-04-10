<?php

namespace App\Feature\OptionsDataSync\Tests\E2E;

use App\Models\OptionsLiveData;
use Carbon\Carbon;
use Tests\TestCase;

class GetOptionsDataBySymbolEarliestExpiryTest extends TestCase
{
    public function test_get_options_data_count_is_accurate(): void
    {
        Carbon::setTestNow(now());

        $earlyOptionsLiveData = OptionsLiveData::factory()
            ->count(fake()->numberBetween(5, 10))
            ->sequence(fn ($sequence) => ['strike' => 300 + $sequence->index])
            ->create([
                'symbol' => 'SPY',
                'expiry_date' => now()
            ]);

        $nextOptionsLiveData = OptionsLiveData::factory()
            ->count(fake()->numberBetween(5, 10))
            ->sequence(fn ($sequence) => ['strike' => 300 + $sequence->index])
            ->create([
                'symbol' => 'SPY',
                'expiry_date' => now()->addMonth()
            ]);

        $response = $this->getJson(
            route('get.options.live-data-earliest-expiry', [
                'symbol' => 'SPY'
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonCount($earlyOptionsLiveData->count());
    }
}
