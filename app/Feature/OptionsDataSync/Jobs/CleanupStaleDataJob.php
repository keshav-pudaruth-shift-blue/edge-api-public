<?php

namespace App\Feature\OptionsDataSync\Jobs;

use App\Repositories\OptionsLiveDataLatestRepository;
use App\Repositories\OptionsLiveDataRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class CleanupStaleDataJob implements ShouldQueue, ArtisanDispatchable
{
    use Dispatchable;
    use Queueable;

    public int $retries = 3;

    public int $backoff = 60;

    public function handle(): void
    {
        Log::info('CleanupStaleDataJob started');
        $allLatestOptionsData = app(OptionsLiveDataLatestRepository::class)->getAllOptionsDataById();
        $allLatestOptionsDataIds = $allLatestOptionsData->pluck('id')->toArray();
        Log::info('CleanupStaleDataJob - Count current latest options data: ', [
            'count' => count($allLatestOptionsDataIds)
        ]);
        app(OptionsLiveDataRepository::class)->deleteOptionsDataWhereIdNotIn($allLatestOptionsDataIds);
        Log::info('CleanupStaleDataJob finished');
    }
}
