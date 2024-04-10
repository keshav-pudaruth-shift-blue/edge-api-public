<?php

namespace App\Feature\OptionsDataSync\Tests\Jobs;

use App\Feature\OptionsDataSync\Jobs\CleanupExpiredDataJob;
use App\Models\OptionsLiveData;
use Tests\TestCase;

class CleanupExpiredDataJobTest extends TestCase
{
    public function test_option_data_that_has_expired_is_deleted(): void
    {
        $currentOptionsData = OptionsLiveData::factory()->create([
            'expiry_date' => now()->addDay(),
        ]);

        $expiredOptionsData = OptionsLiveData::factory()->create([
            'expiry_date' => now()->subDay(),
        ]);

        dispatch(new CleanupExpiredDataJob());

        $this->assertDatabaseHas('options_live_data', [
            'id' => $currentOptionsData->id,
        ]);

        $this->assertDatabaseMissing('options_live_data', [
            'id' => $expiredOptionsData->id,
        ]);
    }
}
