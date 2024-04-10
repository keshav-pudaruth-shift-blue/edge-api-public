<?php

namespace App\Feature\IBOptionsDataSync\Tests\Integration\Job;

use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolOptionsContractsChainJob;
use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Feature\IBOptionsDataSync\Services\TradingHoursService;
use App\Feature\IBOptionsDataSync\Tests\IbOptionsTestCase;
use App\Models\InteractiveBrokers\OptionContractDefinition;
use App\Models\OptionsContracts;
use App\Models\OptionsContractsTradingHours;
use App\Models\OptionsContractsTypeEnum;
use App\Models\SymbolList;
use App\Repositories\OptionsContractsRepository;
use App\Repositories\OptionsContractsWithTradingHoursRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncSymbolOptionsContractsChainJobTest extends IbOptionsTestCase
{
    private string $ibGatewayBaseUri;

    public function setUp(): void
    {
        parent::setUp();

        $this->ibGatewayBaseUri = config('ib-gateway.base_uri');
    }

    public function test_job_must_not_save_contracts_when_underlying_price_is_zero()
    {
        $symbol = SymbolList::factory()->create([
            'name' => 'SPY',
            'ib_contract_id' => 756733,
        ]);

        Http::fake([
            'https://api.polygon.io/v2/aggs/ticker/SPY/prev' => Http::response([
                'ticker' => 'SPY',
                'status' => 'OK',
                'queryCount' => 0,
                'resultsCount' => 0,
                'adjusted' => true
            ], 200)
        ]);

        Http::fake([
            $this->ibGatewayBaseUri . 'contract-chain/' . $symbol->name . '/' . $symbol->ib_contract_id => Http::response(
                $this->generateOptionChainData($symbol),
                200
            )
        ]);

        dispatch(new SyncSymbolOptionsContractsChainJob($symbol));

        $this->assertTrue(true);
    }

    public function test_job_must_save_underlying_contracts_when_underlying_price_is_above_zero()
    {
        Http::fake([
            'https://api.polygon.io/v2/aggs/ticker/SPY/prev' => Http::response([
                'ticker' => 'SPY',
                'status' => 'OK',
                'queryCount' => 1,
                'resultsCount' => 1,
                'adjusted' => true,
                'results' => [
                    [
                        'v' => 400,
                        'vw' => 400,
                        'o' => 400,
                        'c' => 400,
                        'h' => 400,
                        'l' => 400,
                        't' => 400,
                        'n' => 400,
                    ]
                ]
            ], 200)
        ]);
    }

    public function test_job_option_contracts_when_absent_from_database()
    {
        $ibGatewayService = app(IBGatewayService::class);

        $symbol = SymbolList::factory()->create([
            'name' => 'SPX',
            'ib_contract_id' => 756733,
        ]);

        //Fake prev close api
        Http::fake([
            'https://api.polygon.io/v2/aggs/ticker/SPY/prev' => Http::response([
                'ticker' => 'SPY',
                'status' => 'OK',
                'queryCount' => 1,
                'resultsCount' => 1,
                'adjusted' => true,
                'results' => [
                    [
                        'v' => 400,
                        'vw' => 400,
                        'o' => 400,
                        'c' => 400,
                        'h' => 400,
                        'l' => 400,
                        't' => 400,
                        'n' => 400,
                    ]
                ]
            ])
        ]);

        //Fake option contract data from IB Gateway
        $optionContractCall = $this->generateOptionContractDefinition($symbol, now()->addDay(), 4000, OptionsContractsTypeEnum::CALL);
        Http::fake([
            '*'.$ibGatewayService->constructOptionsContractsURL($symbol->name, now(), OptionsContractsTypeEnum::CALL) => Http::response(
                [$optionContractCall]
            )
        ]);

        $optionContractPut = $this->generateOptionContractDefinition($symbol, now()->addDay(), 4000, OptionsContractsTypeEnum::PUT);

        Http::fake([
            '*'.$ibGatewayService->constructOptionsContractsURL($symbol->name, now(), OptionsContractsTypeEnum::PUT) => Http::response(
                [$optionContractPut]
            )
        ]);

        dispatch(new SyncSymbolOptionsContractsChainJob($symbol));

        $this->assertDatabaseHas(app(OptionsContractsRepository::class)->getModel()->getTable(), [
            'symbol' => $symbol->name,
            'expiry_date' => now(OptionsContracts::TIMEZONE_USA)->startOfDay()->toDateTimeString(),
            'option_type' => OptionsContractsTypeEnum::CALL,
            'strike_price' => $optionContractCall['contract']['strike'],
        ]);

        $this->assertDatabaseHas(app(OptionsContractsRepository::class)->getModel()->getTable(), [
            'symbol' => $symbol->name,
            'expiry_date' => now(OptionsContracts::TIMEZONE_USA)->startOfDay()->toDateTimeString(),
            'option_type' => OptionsContractsTypeEnum::PUT,
            'strike_price' => $optionContractPut['contract']['strike'],
        ]);
    }

    public function test_job_must_save_trading_hours_when_absent_from_database()
    {
        $ibGatewayService = app(IBGatewayService::class);

        $symbol = SymbolList::factory()->create([
            'name' => 'SPX',
            'ib_contract_id' => 756733,
        ]);

        //Fake prev close api
        Http::fake([
            'https://api.polygon.io/v2/aggs/ticker/SPY/prev' => Http::response([
                'ticker' => 'SPY',
                'status' => 'OK',
                'queryCount' => 1,
                'resultsCount' => 1,
                'adjusted' => true,
                'results' => [
                    [
                        'v' => 400,
                        'vw' => 400,
                        'o' => 400,
                        'c' => 400,
                        'h' => 400,
                        'l' => 400,
                        't' => 400,
                        'n' => 400,
                    ]
                ]
            ])
        ]);

        //Fake option contract data from IB Gateway
        $optionContractCall = $this->generateOptionContractDefinition($symbol, now()->addDay(), 4000, OptionsContractsTypeEnum::CALL);
        Http::fake([
            '*'.$ibGatewayService->constructOptionsContractsURL($symbol->name, now(), OptionsContractsTypeEnum::CALL) => Http::response(
                [$optionContractCall]
            )
        ]);

        $optionContractPut = $this->generateOptionContractDefinition($symbol, now()->addDay(), 4000, OptionsContractsTypeEnum::PUT);

        Http::fake([
            '*'.$ibGatewayService->constructOptionsContractsURL($symbol->name, now(), OptionsContractsTypeEnum::PUT) => Http::response(
                [$optionContractPut]
            )
        ]);

        dispatch(new SyncSymbolOptionsContractsChainJob($symbol));

        $this->assertDatabaseHas(app(OptionsContractsWithTradingHoursRepository::class)->getModel()->getTable(), [
            'timezone' => OptionsContractsTradingHours::TIMEZONE_USA,
            'start_datetime' => now(OptionsContractsTradingHours::TIMEZONE_USA)->hour(8)->minutes(30)->seconds(0)->setTimezone(config('app.timezone'))->toDateTimeString(),
            'end_datetime' => now(OptionsContractsTradingHours::TIMEZONE_USA)->hour(16)->minutes(0)->seconds(0)->setTimezone(config('app.timezone'))->toDateTimeString(),
        ]);

        $this->assertDatabaseHas(app(OptionsContractsWithTradingHoursRepository::class)->getModel()->getTable(), [
            'timezone' => OptionsContractsTradingHours::TIMEZONE_USA,
            'start_datetime' => now(OptionsContractsTradingHours::TIMEZONE_USA)->hour(8)->minutes(30)->seconds(0)->addDay()->setTimezone(config('app.timezone'))->toDateTimeString(),
            'end_datetime' => now(OptionsContractsTradingHours::TIMEZONE_USA)->hour(16)->minutes(0)->seconds(0)->addDay()->setTimezone(config('app.timezone'))->toDateTimeString(),
        ]);
    }
}
