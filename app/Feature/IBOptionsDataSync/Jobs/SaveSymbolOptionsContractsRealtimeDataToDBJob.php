<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Job\BasicJob;
use App\Repositories\OptionsContractsHistoricalDataRepository;
use App\Repositories\OptionsContractsRepository;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SaveSymbolOptionsContractsRealtimeDataToDBJob extends BasicJob implements ArtisanDispatchable
{
    /**
     * @var OptionsContractsRepository
     */
    private $optionsContractsRepository;

    /**
     * @var OptionsContractsHistoricalDataRepository
     */
    private $optionsContractsHistoricalDataRepository;

    public function handle()
    {
        $this->initializeServices();

        $optionContracts = $this->optionsContractsRepository->getOptionContractsWithinTradingHours();

        foreach ($optionContracts as $optionContract) {
            $realTimeData = $this->optionsContractsHistoricalDataRepository->getRealTimeDataByContractId(
                $optionContract->contract_id);
            if (!empty($realTimeData)) {
                $realTimeDataTicks = $realTimeData['ticks'];
                if(!empty($realTimeDataTicks)) {
                    $lastTick = end($realTimeDataTicks);

                } else {
                    Log::info("SaveSymbolOptionsContractsRealtimeDataToDBJob:: No ticks for contract id {$optionContract->contract_id}");
                }
            }
        }
    }

    private function initializeServices(): void
    {
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->optionsContractsHistoricalDataRepository = app(OptionsContractsHistoricalDataRepository::class);
    }
}
