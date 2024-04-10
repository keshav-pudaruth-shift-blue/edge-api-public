<?php

namespace App\Feature\IBOptionsDataSync\Tests\Integration\Job;

use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolContractIdJob;
use App\Models\SymbolList;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncSymbolContractIdJobTest extends TestCase
{
    private $ibGatewayBaseUri;

    public function setUp(): void
    {
        parent::setUp();

        $this->ibGatewayBaseUri = config('ib-gateway.base_uri');
    }

    public function test_job_must_call_api()
    {
        $symbolList = SymbolList::factory()->create([
            'name' => 'SPY'
        ]);

        Http::fake([
            $this->ibGatewayBaseUri.'contract-search/' . $symbolList->name => Http::response([
                [
                    'contract' => [
                        'conId' => 123456,
                        'currency' => 'USD',
                        'secType' => 'STK'
                    ]
                ]
            ], 200)
        ]);

        dispatch(new SyncSymbolContractIdJob());

        Http::assertSentCount(1);
    }

    public function test_job_must_set_has_options_to_zero_when_no_contract_is_found()
    {
        $symbolList = SymbolList::factory()->create([
            'name' => 'SPY'
        ]);

        Http::fake([
            $this->ibGatewayBaseUri.'contract-search/' . $symbolList->name => Http::response([
                [
                    'contract' => [
                        'conId' => 123456,
                        'currency' => 'EUR',
                        'secType' => 'STK'
                    ]
                ]
            ], 200)
        ]);

        dispatch(new SyncSymbolContractIdJob());

        $this->assertDatabaseHas((new SymbolList())->getTable(), [
            'name' => $symbolList->name,
            'has_options' => 0
        ]);
    }

    public function test_job_must_save_contract_id_when_matched()
    {
        $contractId = $this->faker->randomNumber(6);

        $symbolList = SymbolList::factory()->create([
            'name' => 'SPY'
        ]);

        Http::fake([
            $this->ibGatewayBaseUri.'contract-search/' . $symbolList->name => Http::response([
                [
                    'contract' => [
                        'conId' => $contractId,
                        'currency' => 'USD',
                        'secType' => 'STK'
                    ]
                ]
            ], 200)
        ]);

        dispatch(new SyncSymbolContractIdJob());

        $this->assertDatabaseHas((new SymbolList())->getTable(), [
            'name' => $symbolList->name,
            'ib_contract_id' => $contractId
        ]);
    }

    public function test_job_must_skip_symbol_when_unmatched()
    {
        $symbolList = SymbolList::factory()->create([
            'name' => 'SPY'
        ]);

        Http::fake([
            $this->ibGatewayBaseUri.'contract-search/' . $symbolList->name => Http::response([
            ], 200)
        ]);

        dispatch(new SyncSymbolContractIdJob());

        $this->assertDatabaseHas((new SymbolList())->getTable(), [
            'name' => $symbolList->name,
            'ib_contract_id' => null,
            'has_options' => 1
        ]);
    }
}
