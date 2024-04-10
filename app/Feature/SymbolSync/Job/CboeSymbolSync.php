<?php

namespace App\Feature\SymbolSync\Job;

use App\Repositories\SymbolListRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class CboeSymbolSync implements ShouldQueue, ArtisanDispatchable
{
    use Dispatchable;
    use Queueable;

    public $tries = 3;

    public function handle()
    {
        $cboeDataList = Http::withOptions(['http_errors' => true])->get(
            "https://cdn.cboe.com/api/global/delayed_quotes/symbol_book/symbol-book.json"
        );

        $cboeDataList = collect($cboeDataList->json()['data']);

        $symbolListRepository = app(SymbolListRepository::class);

        $cboeDataList->chunk(100)->each(function ($cboeDataListChunk) use ($symbolListRepository) {
            $symbolListRepository->getQuery()->upsert($cboeDataListChunk->toArray(), ['name']);
        });
    }

    public function failed($exception)
    {
        Log::error('CboeSymbolSync::failed - Error syncing symbols', [
            'exception' => $exception->getMessage()
        ]);
    }
}
