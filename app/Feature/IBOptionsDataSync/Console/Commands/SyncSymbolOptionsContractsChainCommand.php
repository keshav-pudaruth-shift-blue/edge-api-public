<?php

namespace App\Feature\IBOptionsDataSync\Console\Commands;

use App\Models\SymbolList;
use App\Repositories\SymbolListRepository;
use Illuminate\Console\Command;
use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolOptionsContractsChainJob;
class SyncSymbolOptionsContractsChainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ib:sync-symbol-options-contracts-chain {--all} {symbol?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync symbol\'s options contracts chain';

    /**
     * @var SymbolListRepository
     */
    private $symbolListRepository;

    /**
     * Execute the console command.
     *
     * @return void
     */

    public function handle(): void
    {
        $this->info('Syncing symbol options contracts chain - Start');

        $this->symbolListRepository = app(SymbolListRepository::class);

        if (!empty($this->argument('symbol'))) {
            $symbol = $this->symbolListRepository->getBySymbol(strtoupper($this->argument('symbol')));
            $this->info('Symbol found - ' . $symbol->name);
            $this->syncSymbolOptionsContractsChain($symbol);
        } else if(!empty($this->option('all')) || $this->confirm('Symbol not found. Do you want to sync all enabled symbols?')) {
            $this->warn('Syncing all enabled symbols');
            $symbols = $this->symbolListRepository->getEnabledSymbols();
            foreach($symbols as $symbol) {
                $this->info('Syncing symbol - ' . $symbol->name);
                $this->syncSymbolOptionsContractsChain($symbol);
            }
        }

        $this->info('Syncing symbol options contracts chain - End');
    }

    private function syncSymbolOptionsContractsChain(SymbolList $symbol)
    {
        if(app()->environment('local')) {
            dispatch(new SyncSymbolOptionsContractsChainJob($symbol))->onConnection('sync');
        } else {
            dispatch(new SyncSymbolOptionsContractsChainJob($symbol))->onQueue('tortoise')->onConnection('redis-tortoise');
        }
    }
}
