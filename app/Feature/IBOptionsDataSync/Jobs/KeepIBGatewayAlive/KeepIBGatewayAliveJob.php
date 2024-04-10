<?php

namespace App\Feature\IBOptionsDataSync\Jobs\KeepIBGatewayAlive;

use App\Feature\IBOptionsDataSync\Jobs\SubSymbolAllOptionsContractsRealtimeDataJob;
use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use App\Repositories\OptionsContractsHistoricalDataRepository;
use App\Repositories\OptionsContractsRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use RenokiCo\PhpK8s\Exceptions\KubeConfigClusterNotFound;
use RenokiCo\PhpK8s\Exceptions\KubeConfigContextNotFound;
use RenokiCo\PhpK8s\Exceptions\KubeConfigUserNotFound;
use RenokiCo\PhpK8s\KubernetesCluster;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class KeepIBGatewayAliveJob extends BasicJob implements ShouldBeUnique, ArtisanDispatchable
{
    public int $tries = 1;
    public int $timeout = 300;

    /**
     * @var IBGatewayService
     */
    private $ibGatewayService;

    /**
     * @var OptionsContractsRepository
     */
    private $optionsContractsRepository;

    /**
     * @var OptionsContractsHistoricalDataRepository
     */
    private $optionsContractsHistoricalDataRepository;

    public function __construct()
    {
        $this->connection = app()->runningUnitTests() ? 'sync' : 'redis-tortoise';
        $this->onQueue('tortoise');
    }

    /**
     * @throws KubeConfigClusterNotFound
     * @throws KubeConfigContextNotFound
     * @throws KubeConfigUserNotFound
     */
    public function handle(): bool
    {
        $this->initializeServices();

        //Call liveness end point on gateway api
        $success = false;

        try {
            $success = $this->ibGatewayService->checkHealth();
            if($success === true) {
                //Check if there's any contracts that are supposed to be live right now.
                if($this->optionsContractsRepository->queryOptionContractsWithinTradingHours()->count() > 0) {
                    //Check liveness of real time contracts
                    $ibRealTimeSubs = $this->ibGatewayService->getListRealTimeSubscriptions();
                    if(!empty($ibRealTimeSubs)) {
                        $optionContractList = $this->optionsContractsRepository->getQuery()->whereIn(
                            'contract_id',
                            $ibRealTimeSubs
                        )->get([
                            'contract_id',
                            'symbol',
                            'strike_price',
                            'option_type',
                            'expiry_date'
                        ]);

                        $liveOptionContractList = $optionContractList->filter(function($row) {
                            return $this->optionsContractsHistoricalDataRepository->checkLivenessRealTimeDataByContractId($row->contract_id);
                        });

                        if(count($liveOptionContractList) === 0) {
                            Log::warning("KeepIBGatewayAlive - No live real time data, restarting services");
                            $success = false;
                        }
                    } else {
                        Log::warning("KeepIBGatewayAlive - No live real time data, restarting services");
                        $success = false;
                    }
                }
            }
        } catch(\Exception $e) {
            Log::error("KeepIBGatewayAlive - Failed to check health of gateway api", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            if($success === false) {
                Log::warning("KeepIBGatewayAlive - Gateway is dead, restarting services");
                Bus::chain([
                    new RestartIBGatewayJob(),
                    new ReconnectIBGatewayAPIJob(),
                    new SubSymbolAllOptionsContractsRealtimeDataJob(),
                ])->dispatch();
            } else {
                Log::info("KeepIBGatewayAlive - Gateway is alive");
            }
        }

        return true;
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
        return ['ib-gateway-keep-alive'];
    }

    private function initializeServices(): void
    {
        $this->ibGatewayService = app(IBGatewayService::class);
        $this->optionsContractsRepository = app(OptionsContractsRepository::class);
        $this->optionsContractsHistoricalDataRepository = app(OptionsContractsHistoricalDataRepository::class);
    }
}
