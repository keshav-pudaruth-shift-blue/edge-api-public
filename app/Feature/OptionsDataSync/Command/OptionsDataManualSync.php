<?php

namespace App\Feature\OptionsDataSync\Command;

use App\Feature\OptionsDataSync\Services\OptionsDataSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptionsDataManualSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'options-data:manual-sync {symbol : Symbol of the option}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync options data manually';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(protected OptionsDataSyncService $optionsDataSyncService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle()
    {
        $symbol = $this->argument('symbol');
        $this->info('Options data manual sync started - ' . $symbol);
        $this->optionsDataSyncService->syncOptionsDataToDatabase($symbol);
        $this->info('Options data manual sync finished');
    }
}
