<?php

namespace App\Feature\OptionsDataSync\Jobs;

use App\Feature\OptionsDataSync\Events\SyncSymbolOptionsDataSuccess;
use App\Feature\OptionsDataSync\Services\OptionsDataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncSymbolOptionsDataJob implements ShouldQueue,ArtisanDispatchable
{
    use Queueable;

    public int $retries = 3;

    public int $backoff = 30;

    public function __construct(protected string $symbol)
    {
    }

    /**
     * @throws \Exception
     */
    public function handle(OptionsDataSyncService $optionsDataSyncService)
    {
        $optionsDataSyncService->syncOptionsDataToDatabase($this->symbol);
        event(new SyncSymbolOptionsDataSuccess($this->symbol));
    }

    public function failed($exception)
    {
        Log::error('SyncSymbolOptionsDataJob::failed - Error syncing options data', [
            'symbol' => $this->symbol,
            'exception' => $exception->getMessage()
        ]);
    }

}
