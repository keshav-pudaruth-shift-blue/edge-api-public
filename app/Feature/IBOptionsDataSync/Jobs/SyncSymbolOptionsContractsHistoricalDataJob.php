<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Events\OptionsContractHistoricalDataUpdated;
use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use App\Models\OptionsContracts;
use App\Models\OptionsContractsHistoricalData;
use App\Repositories\OptionsContractsHistoricalDataRepository;
use App\Repositories\OptionsContractsRepository;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;
use Illuminate\Queue\Middleware\RateLimited;
class SyncSymbolOptionsContractsHistoricalDataJob extends BasicJob implements ArtisanDispatchable
{

    /**
     * @var OptionsContractsRepository
     */
    protected $optionsContractsRepository;

    /**
     * @var OptionsContractsHistoricalDataRepository
     */
    protected $optionsContractsHistoricalDataRepository;

    /**
     * @var IBGatewayService
     */
    protected IBGatewayService $ibGatewayService;

    public function __construct(public int $contractId, protected string $interval = '1 min')
    {
        $this->queue = app()->runningUnitTests() ? 'sync' : 'ib-gateway-historical-data';
        $this->connection = app()->runningUnitTests() ? 'sync' : 'redis-ib-historical-data';
    }

    public function handle(): void
    {
        $this->initializeServices();

        Log::debug("Syncing historical data for contract id: $this->contractId");

        $latestHistoricalData = $this->optionsContractsHistoricalDataRepository->getLatestHistoricalData(
            $this->contractId
        );

        //Get since trading session start
        $optionsContract = $this->optionsContractsRepository->getQuery()
            ->with('relSymbol', 'tradingHours')
            ->where('contract_id', '=', $this->contractId)
            ->first();

        if ($latestHistoricalData) {
            Log::debug("Latest historical data for contract id: $this->contractId is: " . $latestHistoricalData->datetime);

            $this->fetchAndSaveHistoricalDataFromIBGatewayService($optionsContract, $latestHistoricalData->datetime);
        } else {
            Log::info("No historical data for contract id: $this->contractId, getting since trading session start");

            if ($optionsContract) {
                $currentTradingHour = $optionsContract->tradingHours->where(
                    'start_datetime',
                    '<=',
                    now()->toDateTimeString()
                )
                    ->where('end_datetime', '>=', now()->toDateTimeString())
                    ->first();

                if ($currentTradingHour) {
                    //Set high time interval to prevent IB overload
                    $this->interval = OptionsContractsHistoricalData::INTERVAL_5_MIN;
                    $this->fetchAndSaveHistoricalDataFromIBGatewayService($optionsContract, $currentTradingHour->start_datetime);

                } else {
                    Log::info("No current trading hour found for contract id: $this->contractId");
                }
            } else {
                Log::error("No options contract found for contract id: $this->contractId");
            }
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [new RateLimited('ib-gateway-historical-data')];
    }


    /**
     * @param OptionsContracts $optionContract
     * @param Carbon $startDatetime
     */
    private function fetchAndSaveHistoricalDataFromIBGatewayService(OptionsContracts $optionContract, Carbon $startDatetime)
    {
        $timePeriod = now()->diffInSeconds($startDatetime);
        //Get data from ib gateway service
        Log::debug("Getting historical data for contract id: $this->contractId since trading session start", [
            'timePeriod' => $timePeriod,
            'contractId' => $this->contractId,
        ]);
        $ibHistoricalData = $this->ibGatewayService->getOptionContractHistoricalDataByContractId(
            $this->contractId,
            "CBOE",
            $timePeriod . ' S',
            $this->interval
        );
        //Save data to database
        if ($ibHistoricalData && (!isset($ibHistoricalData['error']) || Arr::get($ibHistoricalData,'status', 200) !== 422)) {
            $this->saveHistoricalData($ibHistoricalData);

            //Push event to run analysis jobs
            event(new OptionsContractHistoricalDataUpdated($this->contractId));
        } else {
            Log::error("No historical data for contract id on IB: $this->contractId");
        }
    }

    private function saveHistoricalData(array $ibHistoricalData)
    {
        Log::debug("Saving historical data for contract id: $this->contractId", [
            'ibHistoricalData' => $ibHistoricalData,
        ]);
        $parsedIbHistoricalData = array_map(function ($data) {
            return [
                'options_contracts_id' => $this->contractId,
                'datetime' => Carbon::createFromFormat(
                    OptionsContractsHistoricalData::DATETIME_FORMAT_USA,
                    $data['time']
                )->setTimezone('UTC')->toDateTimeString(),
                'open' => $data['open'],
                'high' => $data['high'],
                'low' => $data['low'],
                'close' => $data['close'],
                'volume' => $data['volume'],
            ];
        }, $ibHistoricalData);

        $this->optionsContractsHistoricalDataRepository->getQuery()->upsert($parsedIbHistoricalData, [
            'options_contracts_id',
            'datetime',
        ]);
    }

    private function initializeServices(): void
    {
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->optionsContractsHistoricalDataRepository = app(OptionsContractsHistoricalDataRepository::class);
        $this->ibGatewayService = app(IBGatewayService::class);
    }
}
