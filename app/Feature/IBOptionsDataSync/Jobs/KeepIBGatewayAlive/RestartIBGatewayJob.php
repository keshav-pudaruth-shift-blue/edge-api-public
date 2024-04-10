<?php

namespace App\Feature\IBOptionsDataSync\Jobs\KeepIBGatewayAlive;

use App\Job\BasicJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use RenokiCo\PhpK8s\KubernetesCluster;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class RestartIBGatewayJob extends BasicJob implements ArtisanDispatchable
{
    public int $timeout = 150;

    public function handle(): void
    {
        //IB gateway
        Log::info("RestartIBGatewayJob - Restarting IB Gateway");
        $cluster = KubernetesCluster::fromKubeConfigYaml(base64_decode(env('KUBECONFIG_BASE64')));
        $ibGatewayDeployment = $cluster->getDeploymentByName('edge-ib-gateway', 'shift');
        $ibGatewayDeployment->scale(0);
        sleep(2);
        $ibGatewayDeployment->scale(1);

        //Retry twice for IB gateway readiness
        $success = false;
        for($i=1;$i<=4;$i++) {
            sleep(35);
            if($ibGatewayDeployment->getReadyReplicasCount() >= 1) {
                Log::info("RestartIBGatewayJob - IB Gateway is ready");
                $success=true;
                break;
            } else {
                Log::error("RestartIBGatewayJob - IB Gateway is not ready");
            }
        }

        if($success===false) {
            $this->job->markAsFailed();
        }
    }

    public function tags(): array
    {
        return ['ib-gateway-keep-alive', 'ib-gateway-keep-alive:restart-ib-gateway'];
    }
}
