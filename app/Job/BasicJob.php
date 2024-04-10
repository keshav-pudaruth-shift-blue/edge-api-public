<?php

namespace App\Job;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BasicJob implements ShouldQueue
{
    use Queueable;
    use Dispatchable;
    use Batchable;
    use InteractsWithQueue;

    public int $tries=1;

    public function failed(\Throwable $throwable)
    {
        Log::error('BasicJob::failed - Error running job', [
            'job' => get_class($this),
            'exception' => $throwable->getMessage()
        ]);

        if(app()->bound('sentry') && app()->environment('production')) {
            app('sentry')->captureException($throwable);
        }

    }
}
