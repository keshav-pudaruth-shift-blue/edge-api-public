<?php

namespace App\Feature\IBOptionsDataSync\Tests\Integration\Job;

use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolOptionsContractsHistoricalDataJob;
use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Feature\IBOptionsDataSync\Tests\IbOptionsTestCase;
use App\Models\OptionsContracts;
use App\Models\OptionsContractsHistoricalData;
use App\Models\OptionsContractsTradingHours;
use App\Models\OptionsContractsTypeEnum;
use App\Models\OptionsContractsWithTradingHours;
use App\Models\SymbolList;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SyncSymbolOptionsContractsHistoricalDataJobTest extends IbOptionsTestCase
{
    /**
     * @var string
     */
    protected $ibGatewayBaseUri;

    /**
     * @var IBGatewayService
     */
    protected $ibGatewayService;

    public function setUp(): void
    {
        parent::setUp();

        $this->ibGatewayBaseUri = config('ib-gateway.base_uri');
        $this->ibGatewayService = app(IBGatewayService::class);
    }

    public function test_job_must_save_data_when_data_present_in_database()
    {
        Carbon::setTestNow(now());

        $symbol = SymbolList::factory()->create([
            'name' => 'SPY',
            'ib_contract_id' => 756733,
        ]);

        $optionsContract = OptionsContracts::factory()->create([
            'symbol' => $symbol->name,
            'option_type' => OptionsContractsTypeEnum::CALL,
            'strike_price' => 400,
            'expiry_date' => now()->addDay()
        ]);

        $latestOptionsContractsHistoricalData = OptionsContractsHistoricalData::factory()->create([
            'options_contracts_id' => $optionsContract->contract_id,
            'datetime' => now()->subMinutes(2)
        ]);

        $newestHistoricalData = $this->generateHistoricalData(now()->subMinutes(5), now());

        Http::fake([
            '*'. $this->ibGatewayService->constructOptionContractHistoricalDataByStrikeURL($optionsContract->id).'*' => Http::response(
                $newestHistoricalData
            )
        ]);

        dispatch(new SyncSymbolOptionsContractsHistoricalDataJob($optionsContract->id));

        foreach($newestHistoricalData as $historicalDataRow) {
            $this->assertDatabaseHas('options_contracts_historical_data', [
                'options_contracts_id' => $optionsContract->contract_id,
                'datetime' => Carbon::createFromFormat(OptionsContractsHistoricalData::DATETIME_FORMAT_USA,$historicalDataRow['time'])->toDateTimeString(),
            ]);
        }
    }

    public function test_job_must_save_data_from_start_of_trading_session_when_data_absent_in_database()
    {
        $symbol = SymbolList::factory()->create([
            'name' => 'SPY',
            'ib_contract_id' => 756733,
        ]);

        $optionsContract = OptionsContracts::factory()->create([
            'symbol' => $symbol->name,
            'option_type' => OptionsContractsTypeEnum::CALL,
            'strike_price' => 400,
            'expiry_date' => now()->addDay()
        ]);

        $tradingHour = OptionsContractsTradingHours::factory()->create();

        OptionsContractsWithTradingHours::create([
            'options_contracts_id' => $optionsContract->id,
            'options_contracts_trading_hours_id' => $tradingHour->id
        ]);

        $newestHistoricalData = $this->generateHistoricalData(now()->subMinutes(10), now());

        Http::fake([
            '*'. $this->ibGatewayService->constructOptionContractHistoricalDataByStrikeURL($optionsContract->id).'*' => Http::response(
                $newestHistoricalData
            )
        ]);

        dispatch(new SyncSymbolOptionsContractsHistoricalDataJob($optionsContract->id));

        foreach($newestHistoricalData as $historicalDataRow) {
            $this->assertDatabaseHas('options_contracts_historical_data', [
                'options_contracts_id' => $optionsContract->id,
                'datetime' => Carbon::createFromFormat(OptionsContractsHistoricalData::DATETIME_FORMAT_USA,$historicalDataRow['time'])->toDateTimeString(),
            ]);
        }
    }
}
