<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\CuriousSignals\Models\CuriousSignals;
use App\Feature\CuriousSignals\Services\CuriousSignalService;
use App\Feature\Discord\Services\DiscordAlertService;
use App\Feature\IBOptionsDataSync\Services\TradingHoursService;
use App\Job\BasicJob;
use App\Repositories\OptionsContractsHistoricalDataRepository;
use App\Repositories\OptionsContractsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class AnalyzeRealtimeDataOptionsContractJob extends BasicJob implements ArtisanDispatchable
{
    public $queue = 'curious-signals';

    /**
     * @var DiscordAlertService
     */
    private $discordAlertService;

    /**
     * @var OptionsContractsRepository
     */
    private $optionsContractsRepository;

    /**
     * @var OptionsContractsHistoricalDataRepository
     */
    private $optionsContractsHistoricalDataRepository;

    /**
     * @var CuriousSignalService
     */
    private $curiousSignalService;

    /**
     * @var TradingHoursService
     */
    private $tradingHoursService;

    public function __construct(public int $contractId)
    {
    }

    /**
     * @throws \Exception
     */
    public function handle(): bool
    {
        //use Grubbs' test, also called the ESD method (extreme studentized deviate),
        //https://www.graphpad.com/quickcalcs/grubbs1/
        $this->initializeServices();

        //Skip if realtime data is missing
        if($this->optionsContractsHistoricalDataRepository->checkLivenessRealTimeDataByContractId($this->contractId) === false)
        {
            Log::debug("AnalyzeRealtimeDataOptionsContractJob:: Realtime data is missing for contract id {$this->contractId}", [
                'contract_id' => $this->contractId
            ]);
            return 0;
        }

        $realTimeData = $this->optionsContractsHistoricalDataRepository->getRealTimeDataByContractId($this->contractId);

        if (!empty($realTimeData)) {
            $realTimeDataTicks = $realTimeData['ticks'];
            $contract = $this->optionsContractsRepository->getByContractId($this->contractId);
            if (!empty($realTimeDataTicks)) {
                $result = $this->curiousSignalService->handle(
                    $realTimeDataTicks,
                    now()->subMinutes(15)->seconds(0),
                    now()->seconds(0)
                );

                //Check for extreme standard deviation
                if (!empty($result['grubbs'])) {
                    //Handle regular trading hours

                    switch ($contract->symbol) {
                        case '^SPX':
                            Log::debug(
                                "AnalyzeRealtimeDataOptionsContractJob:: Grubbs test detected high volume for contract id {$this->contractId}",
                                [
                                    'metadata' => $result['grubbs']
                                ]
                            );
                            //Prevent low volume alerts during PM/AM sessions
                            if ($this->tradingHoursService->isRegularTradingHours(
                                ) && $result['grubbs']['volume'] >= 100 || (!$this->tradingHoursService->isRegularTradingHours(
                                    ) && $result['grubbs']['volume'] > 29)) {

                                $lock = Cache::lock("curious-signal-discord-alert-{$contract->id}-{$result['grubbs']['time']}", 890);
                                if($lock->get()) {
                                    //Determine alert type
                                    $alertType = CuriousSignals::SIGNAL_UNUSUAL_ACTIVITY;
                                    if (($this->tradingHoursService->isRegularTradingHours() && $result['grubbs']['volume'] >= 300) ||
                                        (!$this->tradingHoursService->isRegularTradingHours() && $result['grubbs']['volume'] >= 100)) {
                                        $alertType = CuriousSignals::SIGNAL_HIGH_VOLUME;
                                    }

                                    $this->discordAlertService->sendAlert($contract, $alertType, $result['grubbs']);
                                } else {
                                    Log::debug(
                                        "AnalyzeRealtimeDataOptionsContractJob:: Alert Lock is already in place",
                                        [
                                            'metadata' => $result['grubbs']
                                        ]);
                                }
                            } else {
                                Log::debug(
                                    "AnalyzeRealtimeDataOptionsContractJob:: Grubbs test detected high volume for contract id {$this->contractId} but volume is too low",
                                    [
                                        'metadata' => $result['grubbs']
                                    ]
                                );
                            }
                            break;
                        case 'SPY':
                        case 'QQQ':
                            Log::debug(
                                "AnalyzeRealtimeDataOptionsContractJob:: Grubbs test detected high volume for contract id {$this->contractId}",
                                [
                                    'metadata' => $result['grubbs']
                                ]
                            );
                            //Prevent low volume alerts during PM/AM sessions
                            if ($this->tradingHoursService->isRegularTradingHours(
                                ) && $result['grubbs']['volume'] >= 300 || (!$this->tradingHoursService->isRegularTradingHours(
                                    ) && $result['grubbs']['volume'] >= 100)) {

                                $lock = Cache::lock("curious-signal-discord-alert-{$contract->id}-{$result['grubbs']['time']}", 900);
                                if($lock->get()) {
                                    $this->discordAlertService->sendAlert($contract, $result['grubbs']);
                                } else {
                                    Log::debug(
                                        "AnalyzeRealtimeDataOptionsContractJob:: Alert Lock is already in place",
                                        [
                                            'metadata' => $result['grubbs']
                                        ]);
                                }
                            } else {
                                Log::debug(
                                    "AnalyzeRealtimeDataOptionsContractJob:: Grubbs test detected high volume for contract id {$this->contractId} but volume is too low",
                                    [
                                        'metadata' => $result['grubbs']
                                    ]
                                );
                            }
                            break;
                        default:
                            Log::error(
                                "AnalyzeRealtimeDataOptionsContractJob:: Contract symbol {$contract->symbol} is not supported"
                            );
                    }
                }
            } else {
                Log::warning("AnalyzeRealtimeDataOptionsContractJob:: No ticks for contract id {$this->contractId}");
            }
        } else {
            Log::debug("AnalyzeRealtimeDataOptionsContractJob:: No ticks for contract id {$this->contractId}");
        }
        return true;
    }

    public function tags(): array
    {
        $contract = app(OptionsContractsRepository::class)->getByContractId($this->contractId);

        return ['cs', "cs-analyze:{$this->contractId}", "cs-analyze:{$contract->symbol}"];
    }


    private function initializeServices(): void
    {
        $this->tradingHoursService = app(TradingHoursService::class);
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->optionsContractsHistoricalDataRepository = app(OptionsContractsHistoricalDataRepository::class);
        $this->curiousSignalService = app(CuriousSignalService::class);
        $this->discordAlertService = app(DiscordAlertService::class);
    }
}
