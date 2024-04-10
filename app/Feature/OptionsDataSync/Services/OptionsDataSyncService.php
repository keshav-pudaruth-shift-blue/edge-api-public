<?php

namespace App\Feature\OptionsDataSync\Services;

use App\Feature\CBOE\Transformers\OptionsWithGreeksTransformer;
use App\Feature\UnusualWhales\Services\UnusualWhalesAPI;
use App\Repositories\OptionsLiveDataRepository;
use Illuminate\Support\Facades\Log;
use Spatie\ResponseCache\Facades\ResponseCache;

class OptionsDataSyncService
{

    public function __construct(
        protected UnusualWhalesAPI $unusualWhalesAPI,
        protected CBOEOptionGreeksService $cboeOptionGreeksService,
        protected OptionsLiveDataRepository $optionsLiveDataRepository
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getOptionsData(string $symbol): \Illuminate\Support\Collection
    {
        $optionsDataResponse = $this->cboeOptionGreeksService->getOptionChainsWithGreeks($symbol);

        if ($optionsDataResponse->successful()) {
            return collect($optionsDataResponse->json()['data']);
        } else {
            Log::error('OptionsDataSyncService::getOptionsData - Error getting options data from UnusualWhalesAPI', [
                'symbol' => $symbol,
                'response_code' => $optionsDataResponse->status(),
                'response_body' => $optionsDataResponse->body()
            ]);
            return collect();
        }
    }

    /**
     * @throws \Exception
     */
    public function syncOptionsDataToDatabase(string $symbol): bool
    {
        $completeData = $this->getOptionsData($symbol);
        $optionsData = collect($completeData->get('options'));
        if ($optionsData->count() > 0) {
            //Filter out the data without open_interest

            $filteredOptionsData = $optionsData->filter(function ($option) {
                return $option['open_interest'] !== 0;
            });

            //Transform data to match the database
            $transformedOptionsData =
                fractal()->collection($filteredOptionsData)
                ->transformWith(new OptionsWithGreeksTransformer())
                ->toArray();

            $transformedOptionsData = collect($transformedOptionsData)->filter(function($row) {
                return $row['expiry_date']->greaterThanOrEqualTo(now()->startOfDay());
            })->map(function($row) use ($symbol, $completeData) {
                $row['underlying_price'] = $completeData['current_price'];
                return $row;
            });

            Log::info('OptionsDataSyncService::syncOptionsDataToDatabase - Syncing options data to database', [
                'symbol' => $symbol,
                'options_data_count' => $transformedOptionsData->count()
            ]);

            //Insert data to database
            $numberOfUpdateRows = 0;
            $transformedOptionsDataCount = $transformedOptionsData->count();
            $transformedOptionsData->chunk(ceil($transformedOptionsDataCount/5000))
                ->each(function($chunk) use ($numberOfUpdateRows) {
                    if(!$chunk->isEmpty()) {
                        $numberOfUpdateRows += $this->optionsLiveDataRepository->getQuery()->upsert($chunk->toArray(),['symbol', 'expiry_date', 'strike_price', 'type']);
                    }

                });

            if($numberOfUpdateRows > 0) {
                Log::info('OptionsDataSyncService::syncOptionsDataToDatabase - Options data synced to database', [
                    'symbol' => $symbol,
                    'options_data_count' => $numberOfUpdateRows
                ]);
                ResponseCache::forget(route('get.options.live-data', ['symbol' => $symbol]));
                ResponseCache::forget(route('get.options.live-data-earliest-expiry', ['symbol' => $symbol]));
            }

            return true;
        } else {
            Log::warning('OptionsDataSyncService::syncOptionsDataToDatabase - No options data', [
                'symbol' => $symbol
            ]);

            return false;
        }
    }
}
