<?php

namespace App\Feature\IBOptionsDataSync\Tests\Integration\Job;

use App\Feature\IBOptionsDataSync\Jobs\SubSymbolAllOptionsContractsRealtimeDataJob;
use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolOptionsContractsHistoricalDataJob;
use App\Feature\IBOptionsDataSync\Tests\IbOptionsTestCase;
use App\Models\OptionsContracts;
use App\Models\OptionsContractsTradingHours;
use App\Models\OptionsContractsTypeEnum;
use App\Models\OptionsContractsWithTradingHours;
use App\Models\SymbolList;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class SyncSymbolAllOptionsContractsHistoricalDataJobTest extends IbOptionsTestCase
{
    public function test_run_job_without_any_symbols_will_not_throw_exception()
    {
        $this->assertTrue((new SubSymbolAllOptionsContractsRealtimeDataJob())->handle());
    }

    public function test_run_job_with_option_contract_with_current_trading_hours_will_dispatch_job()
    {
        Bus::fake([
            SyncSymbolOptionsContractsHistoricalDataJob::class
        ]);

        $symbol = SymbolList::factory()->create([
            'name' => 'SPY',
        ]);
        $optionContracts = $this->generateOptionContracts($symbol, range(390,400), OptionsContractsTypeEnum::CALL, [
            [
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ]
        ]);

        dispatch(new SubSymbolAllOptionsContractsRealtimeDataJob());

        $optionContractIds = array_map(function($optionContract) {
            return $optionContract->contract_id;
        }, $optionContracts);

        Bus::assertDispatched(SyncSymbolOptionsContractsHistoricalDataJob::class, function ($job) use ($optionContractIds) {
            return in_array($job->contractId, $optionContractIds);
        });
    }

    public function test_jobs_with_symbols_with_sync_historical_data_disabled_must_not_be_dispatched()
    {
        Bus::fake([
            SyncSymbolOptionsContractsHistoricalDataJob::class
        ]);

        $symbol = SymbolList::factory()->create([
            'name' => 'SPY',
            'sync_options_historical_data_enabled' => true,
        ]);

        $symbolDisabled = SymbolList::factory()->create([
            'name' => 'MSFT',
            'sync_options_historical_data_enabled' => false,
        ]);

        $optionContracts = $this->generateOptionContracts($symbol, range(390,400), OptionsContractsTypeEnum::CALL, [
            [
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ]
        ]);

        $falseContracts = $this->generateOptionContracts($symbolDisabled, range(200,210), OptionsContractsTypeEnum::CALL, [
            [
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ]
        ]);

        dispatch(new SubSymbolAllOptionsContractsRealtimeDataJob());

        $falseContractsIds = array_map(function($optionContract) {
            return $optionContract->contract_id;
        }, $falseContracts);

        Bus::assertNotDispatched(SyncSymbolOptionsContractsHistoricalDataJob::class, function ($job) use ($falseContractsIds) {
            return in_array($job->contractId, $falseContractsIds);
        });
    }

    public function test_option_contracts_with_expiration_order_greater_than_2_must_be_ignored()
    {
        Carbon::setTestNow(now()->hour(21));

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
                        'c' => 395,
                        'h' => 400,
                        'l' => 400,
                        't' => 400,
                        'n' => 400,
                    ]
                ]
            ])
        ]);

        Bus::fake([
            SyncSymbolOptionsContractsHistoricalDataJob::class
        ]);

        $symbol = SymbolList::factory()->create([
            'name' => 'SPY',
            'sync_options_historical_data_enabled' => true,
        ]);

        $this->generateOptionContracts($symbol, range(390,400), OptionsContractsTypeEnum::CALL, [
            [
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ]
        ]);

        $optionContract405 = $this->generateOptionContracts($symbol, [405], OptionsContractsTypeEnum::CALL, [
            [
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ],
            [
                'start_datetime' => now()->addDay()->subHour(),
                'end_datetime' => now()->addDay()->addHour(),
            ]
        ]);

        $optionContract410 = $this->generateOptionContracts($symbol, [410], OptionsContractsTypeEnum::CALL, [
            [
                'start_datetime' => now()->subHour(),
                'end_datetime' => now()->addHour(),
            ],
            [
                'start_datetime' => now()->addDay()->subHour(),
                'end_datetime' => now()->addDay()->addHour(),
            ],
            [
                'start_datetime' => now()->addDays(2)->subHour(),
                'end_datetime' => now()->addDays(2)->addHour(),
            ]
        ]);

        dispatch(new SubSymbolAllOptionsContractsRealtimeDataJob());

        Bus::assertDispatched(SyncSymbolOptionsContractsHistoricalDataJob::class, function ($job) use ($optionContract405) {
            return $job->contractId === $optionContract405[0]->contract_id;
        });

        Bus::assertNotDispatched(SyncSymbolOptionsContractsHistoricalDataJob::class, function ($job) use ($optionContract410) {
            return $job->contractId === $optionContract410[0]->contract_id;
        });

    }

    /**
     * @param SymbolList $symbol
     * @param array $strikeRange
     * @param OptionsContractsTypeEnum $contractType
     * @param array $schedule
     * @return array
     */
    private function generateOptionContracts(SymbolList $symbol, array $strikeRange, OptionsContractsTypeEnum $contractType, array $schedule): array
    {
        $scheduleList = $optionContractList = [];

        //Create trading hours
        foreach($schedule as $scheduleSlot) {
            $scheduleList[] = (new OptionsContractsTradingHours())->firstOrCreate([
                'start_datetime' => $scheduleSlot['start_datetime'],
                'end_datetime' => $scheduleSlot['end_datetime'],
                'timezone' => 'US/Central'
            ]);
        }

        foreach($strikeRange as $strike) {
            $optionContract = OptionsContracts::factory()->create([
                'symbol' => $symbol->name,
                'strike_price' => $strike,
                'option_type' => $contractType->value,
            ]);

            foreach($scheduleList as $scheduleRow) {
                (new OptionsContractsWithTradingHours([
                    'options_contracts_id' => $optionContract->id,
                    'options_contracts_trading_hours_id' => $scheduleRow->id
                ]))->save();
            }

            $optionContractList[] = $optionContract;
        }

        return $optionContractList;
    }
}
