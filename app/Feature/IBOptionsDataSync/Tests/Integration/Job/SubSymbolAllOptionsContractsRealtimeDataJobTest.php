<?php

namespace App\Feature\IBOptionsDataSync\Tests\Integration\Job;

use App\Feature\IBOptionsDataSync\Jobs\SubSymbolAllOptionsContractsRealtimeDataJob;
use App\Feature\IBOptionsDataSync\Jobs\SubSymbolOptionsContractsRealtimeDataJob;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SubSymbolAllOptionsContractsRealtimeDataJobTest extends TestCase
{
    public function test_normal_has_contract_today()
    {
        Bus::fake(SubSymbolOptionsContractsRealtimeDataJob::class);

        dispatch(new SubSymbolAllOptionsContractsRealtimeDataJob());

        Bus::assertDispatched(SubSymbolOptionsContractsRealtimeDataJob::class);
    }
}
