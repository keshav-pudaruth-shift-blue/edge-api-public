<?php

namespace App\Feature\IBOptionsDataSync\Jobs;

use App\Job\BasicJob;
use App\Repositories\SymbolListRepository;

class SyncSymbolListOptionsContractsGreeksJob extends BasicJob
{
    protected SymbolListRepository $symbolListRepository;

    public function __construct(protected string $symbol, public $forceSyncAll = false)
    {
    }

    public function handle()
    {
        $this->initializeServices();

        //Fetch settings for symbol
        $symbolMetaData = $this->symbolListRepository->getBySymbol($this->symbol);

        //Handle short term expiry contracts

        //Handle medium term expiry contracts

        //Handle long term expiry contracts
    }

    protected function initializeServices()
    {
        //Initialize services
        $this->symbolListRepository = app(SymbolListRepository::class);
    }
}
