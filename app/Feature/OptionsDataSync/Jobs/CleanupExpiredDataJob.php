<?php

namespace App\Feature\OptionsDataSync\Jobs;

use App\Repositories\OptionsLiveDataRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class CleanupExpiredDataJob implements ShouldQueue, ArtisanDispatchable
{
    use Dispatchable;
    use Queueable;

    public int $retries = 3;

    public int $backoff = 60;

    public function handle(): void
    {
        Log::info('CleanupExpiredDataJob started');
        app(OptionsLiveDataRepository::class)->deleteOptionsDataWhereExpiryDate(now());
        Log::info('CleanupExpiredDataJob finished');
    }
}
