<?php

namespace App\Feature\CuriousSignals\Jobs;

use App\Feature\IBOptionsDataSync\Jobs\AnalyzeRealtimeDataOptionsContractJob;
use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class RunCuriousSignalScannerJob extends BasicJob implements ArtisanDispatchable
{
    /**
     * @var IBGatewayService
     */
    private $ibGatewayService;

    public $queue = 'curious-signals';

    public function handle(): bool
    {
        $this->initializeServices();

        //Get list of real time subscriptions from ib gateway api
        $realTimeContractIds = $this->ibGatewayService->getListRealTimeSubscriptions();

        foreach($realTimeContractIds as $contractID) {
            Log::debug('RunCuriousSignalScannerJob - AnalyzeRealtimeDataOptionsContractJob: ' . $contractID);
            dispatch(new AnalyzeRealtimeDataOptionsContractJob($contractID));
        }

        return 0;
    }

    private function initializeServices()
    {
        $this->ibGatewayService = app(IBGatewayService::class);
    }
}
