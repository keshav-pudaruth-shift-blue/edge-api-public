<?php

namespace App\Console\Commands;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Repositories\OptionsContractsHistoricalDataRepository;
use Illuminate\Console\Command;

class IbUnsubDeadContracts extends Command
{
    protected $signature = 'ib:unsub-dead-contracts';

    protected $description = 'Unsubscribe dead contracts';

    public function handle()
    {
        $this->info('Unsubscribing dead contracts...');

        $ibGatewayService = app(IBGatewayService::class);
        $optionsContractsHistoricalDataRepository=app(OptionsContractsHistoricalDataRepository::class);

        $ibRealTimeSubs = $ibGatewayService->getListRealTimeSubscriptions();

        $this->info('Found ' . count($ibRealTimeSubs) . ' real-time subscriptions');

        $ibDeadSubs = array_filter($ibRealTimeSubs,function($contractId) use ($optionsContractsHistoricalDataRepository){
            return $optionsContractsHistoricalDataRepository->checkLivenessRealTimeDataByContractId($contractId) === false;
        });

        array_walk($ibDeadSubs,function($contractId) use ($ibGatewayService){
            $this->info('Unsubscribing contract ' . $contractId);
            $ibGatewayService->unsubscribeRealTimeData($contractId);
        });

        $this->info('Done');
    }
}
