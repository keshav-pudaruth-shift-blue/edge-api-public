<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use App\Models\SymbolList;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SubSymbolOptionsContractsRealtimeDataJob extends BasicJob
{
    public int $tries = 5;

    private string $rateLimiterName = "ib_gateway_realtime_sub";

    public $connection = 'redis-ib-historical-data';

    public $queue = 'ib-gateway-historical-data';

    public function __construct(public $contractId, public SymbolList $symbol)
    {}

    public function handle(): void
    {
        /**
         * @var IBGatewayService $ibGatewayService
         */
        $ibGatewayService = app(IbGatewayService::class);

        $attempted = RateLimiter::attempt($this->rateLimiterName, 60, function() use ($ibGatewayService) {
            Log::debug("SubSymbolOptionsContractsRealtimeDataJob:: Subscribing contract", [
                "contractId" => $this->contractId,
                "symbol" => $this->symbol->name,
                'exchange' => $this->symbol->exchange,
            ]);
            $result = $ibGatewayService->subscribeRealtimeData($this->contractId, $this->symbol->exchange);
            Log::info("SubSymbolOptionsContractsRealtimeDataJob:: Subscribed contract", [
                "contractId" => $this->contractId,
                "symbol" => $this->symbol->name,
                "result" => $result,
                'exchange' => $this->symbol->exchange,
            ]);
        }, 600);

        if ($attempted === false) {
            Log::debug("SubSymbolOptionsContractsRealtimeDataJob:: Rate limit exceeded, retrying in 60 seconds", [
                "contractId" => $this->contractId,
                "symbol" => $this->symbol->name,
            ]);
            $availableInSeconds = RateLimiter::availableIn($this->rateLimiterName);
            $this->release($availableInSeconds+1);
        }
    }

    public function tags(): array
    {
        return [
            'ib-gateway-historical-data',
            'ib-gateway-historical-data:sub-symbol-options-contracts-realtime-data',
            'ib-gateway-historical-data:sub-symbol-options-contracts-realtime-data:' . $this->symbol->name,
            'ib-gateway-historical-data:sub-symbol-options-contracts-realtime-data:' . $this->symbol->name. ':' . $this->contractId,
        ];
    }
}
