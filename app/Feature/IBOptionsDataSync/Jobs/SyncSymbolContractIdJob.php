<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Job\BasicJob;
use App\Models\SymbolList;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SyncSymbolContractIdJob extends BasicJob
{
    public int $timeout = 3600;

    /**
     * @var IBGatewayService
     */
    private $ibGatewayService;

    public function __construct(public SymbolList $symbol)
    {
    }

    public function handle()
    {
        Log::debug('SyncSymbolContractIdJob started', [
            'symbol' => $this->symbol->name
        ]);

        $this->initializeServices();

        try {
            $ibPayload = $this->ibGatewayService->getSearchSymbolContract($this->symbol->name);
        } catch (ConnectException $e) {
            Log::error('SyncSymbolContractIdJob::handle - Error connecting to IB Gateway', [
                'symbol' => $this->symbol->name,
                'exception' => $e->getMessage()
            ]);
            $this->release(60);
        }

        $this->processSymbolInformation($this->symbol, $ibPayload);

        Log::debug('SyncSymbolContractIdJob finished', [
            'symbol' => $this->symbol->name
        ]);
    }

    private function processSymbolInformation(SymbolList $symbol, array $ibPayload)
    {
        if(!empty($ibPayload)) {
            $relevantContractInformation = Arr::where($ibPayload, function ($value, $key) {
                return $value['contract']['currency'] === 'USD' && $value['contract']['secType'] === 'STK';
            });

            Log::debug('Found ' . count($relevantContractInformation) . ' contracts for symbol ' . $symbol->name);

            if(!empty($relevantContractInformation)) {
                $contractId = Arr::first($relevantContractInformation)['contract']['conId'];
                $symbol->ib_contract_id = $contractId;
            } else {
                $symbol->has_options = false;
            }
        } else {
            Log::info('No contract id found for symbol', [
                'symbol' => $symbol->name
            ]);
            $symbol->has_options = false;
        }

        $symbol->save();
    }

    protected function initializeServices()
    {
        $this->ibGatewayService = app(IBGatewayService::class);
    }
}
