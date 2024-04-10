<?php

namespace App\Feature\IBOptionsDataSync\Jobs\KeepIBGatewayAlive;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class ReconnectIBGatewayAPIJob extends BasicJob implements ShouldBeUnique, ArtisanDispatchable
{
    public int $timeout = 200;

    public function handle(): void
    {
        $ibGatewayService = app(IBGatewayService::class);
        $ibGatewayService->reconnectGateway();
        sleep(2);
        $success = false;
        for($i=1;$i<=4;$i++) {
            try {
                $success = $ibGatewayService->checkHealth();
                if($success === true) {
                    Log::info("RestartIBGatewayAPIJob - API is ready");
                    break;
                }
            } catch (\Exception $e) {
                Log::error("RestartIBGatewayAPIJob - API is not ready");
            }
            sleep(35);
        }

        if($success===false) {
            $this->job->markAsFailed();
        }
    }

    public function tags(): array
    {
        return ['ib-gateway-keep-alive', 'ib-gateway-keep-alive:reconnect-ib-gateway-api'];
    }
}
