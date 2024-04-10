<?php

namespace App\Feature\IBOptionsDataSync\Console\Commands;

use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolContractIdJob;
use App\Repositories\SymbolListRepository;
use Illuminate\Console\Command;

class SyncSymbolContractId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ib:sync-symbol-contract-id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync symbol\'s contract id';

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
        $this->info('Syncing symbol contract id - Start');

        $this->symbolListRepository = app(SymbolListRepository::class);

        $progressBar = $this->output->createProgressBar($this->symbolListRepository->queryByMissingContractId()->count());

        $this->symbolListRepository->queryByMissingContractId()
            ->chunk(100, function ($symbols) use ($progressBar) {
                foreach($symbols as $symbol) {
                    dispatch(new SyncSymbolContractIdJob($symbol))->onQueue('ib-gateway');
                    $progressBar->advance();
                }
            });

        $progressBar->finish();

        $this->info('syncing symbol contract id - End');
    }
}
