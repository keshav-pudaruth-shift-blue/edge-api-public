<?php

namespace App\Feature\OptionsDataSync\Tests\Jobs;

use App\Feature\OptionsDataSync\Jobs\CleanupStaleDataJob;
use App\Models\OptionsLiveData;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CleanupStaleDataJobTest extends TestCase
{
    use WithFaker;

    public function test_option_data_that_is_not_latest_is_deleted(): void
    {
        $sameOptionData = [
            'symbol' => $this->faker->word,
            'expiry_date' => now()->addDay()->format('Y-m-d'),
            'strike' => $this->faker->randomNumber(3, true),
            'type' => $this->faker->randomElement(['call', 'put']),
        ];

        $latestOptionsData = OptionsLiveData::factory()->create([
            ...$sameOptionData,
            'id' => 2
        ]);

        $staleOptionsData = OptionsLiveData::factory()->create([
            ...$sameOptionData,
            'id' => 1
        ]);

        dispatch(new CleanupStaleDataJob());

        $this->assertDatabaseHas('options_live_data', [
            'id' => $latestOptionsData->id,
        ]);

        $this->assertDatabaseMissing('options_live_data', [
            'id' => $staleOptionsData->id,
        ]);
    }
}
