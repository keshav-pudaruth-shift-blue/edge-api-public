<?php

namespace App\Feature\IBOptionsDataSync\Tests\Integration\Job;

use App\Models\SymbolList;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SyncSymbolOptionsContractSpecificExpiryJobTest extends TestCase
{
    use WithFaker;

    private $symbol;

    public function setUp(): void
    {
        parent::setUp();

        $this->symbol = 'SPY';

        SymbolList::factory()->create([
            'name' => 'SPY'
        ]);
    }

    public function test_normal()
    {
        //Add a few contracts
        $strike = 400;
        $expiryDate = now();
        $type = $this->faker->randomElement(['C', 'P']);

        $this->artisan('sync-symbol-options-contract-specific-expiry', [
            '--symbol' => $this->symbol,
            '--expiry' => $expiryDate->format('Y-m-d'),
            '--updateInterval' => 1,
            '--strikeRange' => 1
        ]);
    }
}
