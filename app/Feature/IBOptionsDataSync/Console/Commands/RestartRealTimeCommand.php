<?php

namespace App\Feature\IBOptionsDataSync\Console\Commands;

use App\Feature\IBOptionsDataSync\Jobs\KeepIBGatewayAlive\RestartIBGatewayAPIJob;
use App\Feature\IBOptionsDataSync\Jobs\KeepIBGatewayAlive\RestartIBGatewayJob;
use App\Feature\IBOptionsDataSync\Jobs\SubSymbolAllOptionsContractsRealtimeDataJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class RestartRealTimeCommand extends Command
{
    protected $signature = 'ib:rs';

    protected $description = 'Restart microservices and real time data';

    public function handle()
    {
        $this->info('Restarting IB Gateway and IB Gateway API - Start');

        Bus::chain([
            new RestartIBGatewayJob(),
            new RestartIBGatewayApiJob(),
            new SubSymbolAllOptionsContractsRealtimeDataJob(),
        ])->dispatch();

        $this->info('Restarting IB Gateway and IB Gateway API - Done');
    }
}
