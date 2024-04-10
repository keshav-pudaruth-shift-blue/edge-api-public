<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use App\Repositories\OptionsContractsRepository;
use App\Repositories\OptionsContractsTradingHoursRepository;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncSymbolRealTimeDataPerTradingHoursJob extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 1;

    /**
     * @var IBGatewayService
     */
    private $ibGatewayService;

    /**
     * @var OptionsContractsRepository
     */
    private $optionsContractsRepository;

    //Job runs every 15 mins

    public function handle(): bool
    {
        $this->initializeServices();

        $this->unsubscribeContracts();

        $this->subscribeSymbols();

        return true;
    }

    private function subscribeSymbols(): void
    {
        //Get all contracts, ignoring seconds
        $contracts = $this->optionsContractsRepository->getQuery()
            ->whereHas('tradingHours', function($query) {
                $query->where('start_datetime', '<=', now()->seconds(0)->toDateTimeString())
                ->where('end_datetime', '>=', now()->seconds(0)->toDateTimeString());
            })->get();
        if($contracts->count() > 0) {
            Log::info("SyncSymbolRealTimeDataPerTradingHoursJob:: Subscribing contracts");
            dispatch(new SubSymbolAllOptionsContractsRealtimeDataJob());
        } else {
            Log::warning("SyncSymbolRealTimeDataPerTradingHoursJob:: No contracts to subscribe");
        }
    }

    private function unsubscribeContracts(): void
    {
        //Unsubscribe all symbols
        //Get all contracts, ignoring seconds
        $contracts = $this->optionsContractsRepository->getQuery()
            ->whereHas('tradingHours', function($query) {
                $query->where('end_datetime', '<=', now()->seconds(0)->toDateTimeString());
            })->get();
        //Get all symbols that are subscribed
        foreach($contracts as $contract) {
            Log::debug("SyncSymbolRealTimeDataPerTradingHoursJob:: Unsubscribing contract $contract->contract_id");
            //Unsubscribe symbol
            $this->ibGatewayService->unsubscribeRealtimeData($contract->contract_id);
        }

    }

    private function initializeServices()
    {
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->ibGatewayService = app(IBGatewayService::class);
    }
}
