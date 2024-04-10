<?php

namespace App\Feature\OptionsDataSync\Tests\Command;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OptionsDataManualSyncTest extends TestCase
{
    use WithFaker;

    public function testOptionsDataManualSync()
    {
        $this->markTestSkipped('only for development');

        $randomSymbol = $this->faker->randomElement(['SPY', 'QQQ', 'VIX']);
        $this->artisan('options-data:manual-sync '.$randomSymbol)
            ->expectsOutput("Options data manual sync started - $randomSymbol")
            ->expectsOutput('Options data manual sync finished')
            ->assertExitCode(0);

        $this->assertDatabaseHas('options_live_data', [
            'symbol' => $randomSymbol
        ]);
    }

}
