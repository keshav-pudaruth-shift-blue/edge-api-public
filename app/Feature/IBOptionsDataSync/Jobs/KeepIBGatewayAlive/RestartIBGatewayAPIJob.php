<?php

namespace App\Feature\IBOptionsDataSync\Jobs\KeepIBGatewayAlive;

use App\Job\BasicJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use RenokiCo\PhpK8s\KubernetesCluster;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class RestartIBGatewayAPIJob extends BasicJob implements ShouldBeUnique, ArtisanDispatchable
{
    public int $timeout = 200;

    public function handle(): void
    {
        $cluster = KubernetesCluster::fromKubeConfigYaml(base64_decode(env('KUBECONFIG_BASE64')));

        $gatewayAPIDeployment = $cluster->getDeploymentByName('ib-gateway-api', 'shift');
        $gatewayAPIDeployment->scale(0);
        sleep(2);
        $gatewayAPIDeployment->scale(1);
        Log::info("RestartIBGatewayAPIJob - Restarted IB Gateway API");

        $success = false;
        for($i=1;$i<=4;$i++) {
            sleep(35);
            if ($gatewayAPIDeployment->getReadyReplicasCount() >= 1) {
                Log::info("RestartIBGatewayAPIJob - API is ready");
                $success = true;
                break;
            } else {
                Log::error("RestartIBGatewayAPIJob - API is not ready");
            }
        }

        if($success===false) {
            $this->job->markAsFailed();
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping())->dontRelease()];
    }

    public function tags(): array
    {
        return ['ib-gateway-keep-alive', 'ib-gateway-keep-alive:restart-ib-gateway-api'];
    }
}
