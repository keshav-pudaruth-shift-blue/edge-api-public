<?php

namespace App\Feature\IBOptionsDataSync\Services;

use App\Models\OptionsContractsTypeEnum;
use App\Repositories\OptionsContractsWithTradingHoursRepository;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use App\Models\OptionsContracts;
use Illuminate\Support\Facades\Log;

class IBGatewayService
{
    /**
     * @var \Illuminate\Http\Client\PendingRequest
     */
    private $httpClient;

    public function __construct(protected OptionsContractsWithTradingHoursRepository $optionsContractsRepository)
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => config('ib-gateway.base_uri').'ib/',
            'timeout' => config('ib-gateway.timeout'),
            'connect_timeout' => config('ib-gateway.connect_timeout'),
            'http_errors' => false
        ]);
    }

    /**
     * @param string $symbol
     * @param Carbon $expiryDate
     * @param OptionsContractsTypeEnum $type
     * @param float|int $strike
     * @return string
     */
    public function constructOptionsContractByStrikeURL(string $symbol, Carbon $expiryDate, OptionsContractsTypeEnum $type, float|int $strike): string
    {
        return "/contract/$symbol/".$expiryDate->format("Ymd")."/$strike/{$type->value}";
    }

    /**
     * Gets options contract OHLC data
     *
     * @param int $contractId
     * @param string $exchange
     * @param string $duration
     * @param string $interval
     * @return string
     */
    public function constructOptionContractHistoricalDataByStrikeURL(int $contractId, string $exchange = "CBOE", string $duration = "120 S", string $interval = "1 min"): string
    {
        return "/historical-data/$exchange/$contractId?". http_build_query([
            'duration' => $duration,
            'barSize' => $interval
        ]);
    }

    /**
     * Get list of option contracts
     *
     * @param string $symbol
     * @param Carbon $expiryDate
     * @param OptionsContractsTypeEnum $type
     * @return string
     */
    public function constructOptionsContractsURL(string $symbol, Carbon $expiryDate, OptionsContractsTypeEnum $type): string
    {
        return "/contracts/$symbol/".$expiryDate->format("Ymd")."/{$type->value}";
    }

    /**
     * @return bool
     */
    public function checkHealth(): bool
    {
        $response = $this->httpClient->retry(3, 1000)->timeout(30)->get('/time');

        return $response->successful();
    }

    /**
     * @return bool
     */
    public function reconnectGateway(): bool
    {
        $response = $this->httpClient->retry(3, 1000)->timeout(30)->get('/reconnect');

        return $response->successful();
    }

    /**
     * @param string $symbol
     * @param Carbon $expiryDate
     * @param OptionsContractsTypeEnum $type
     * @return array
     */
    public function getOptionsContracts(string $symbol, Carbon $expiryDate, OptionsContractsTypeEnum $type): array
    {
        $response = $this->httpClient->retry(3, 1000)->get($this->constructOptionsContractsURL($symbol, $expiryDate, $type));

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * @param int $contractId
     * @param string $exchange
     * @param string $duration
     * @param string $interval
     * @return array
     */
    public function getOptionContractHistoricalDataByContractId(int $contractId, string $exchange = "CBOE", string $duration = "120 S", string $interval = "1 min"): array
    {
        $response = $this->httpClient->retry(3, 1000)->get($this->constructOptionContractHistoricalDataByStrikeURL($contractId, $exchange, $duration, $interval));

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * @param string $symbol
     * @param Carbon $expiryDate
     * @param OptionsContractsTypeEnum $type
     * @param float|int $strike
     * @return array
     */

    public function getOptionsContractByStrike(string $symbol, Carbon $expiryDate, OptionsContractsTypeEnum $type, float|int $strike): array
    {
        Log::debug("Getting options contract by strike", [
            'symbol' => $symbol,
            'expiryDate' => $expiryDate->format("Ymd"),
            'type' => $type->value,
            'strike' => $strike
        ]);

        $response = $this->httpClient->get($this->constructOptionsContractByStrikeURL($symbol, $expiryDate, $type, $strike));

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * @param Collection $optionsContracts
     * @return Collection
     */
    public function getPoolOptionContractByStrike(Collection $optionsContracts): Collection
    {
        $responses = $this->httpClient->pool(function(Pool $pool) use ($optionsContracts) {
            return $optionsContracts->map(function(OptionsContracts $optionsContract) use ($pool) {
                return $pool->as($optionsContract->id)->get($this->constructOptionsContractByStrikeURL(
                    $optionsContract->symbol,
                    $optionsContract->expiry_date,
                    $optionsContract->option_type,
                    $optionsContract->strike
                ));
            })->toArray();
        });

        return $optionsContracts->map(function(OptionsContracts $optionsContract) use ($responses){
            $response = $responses[$optionsContract->id];

            $optionsContract->optionGreeks = [];

            //Set attribute when successful
            if (isset($response)) {

                $optionsContract->optionGreeks = $response->successful() ? $response->json() : ['error' => $response->body()];
            }

            return $optionsContract;
        });
    }

    /**
     * @param string $symbol
     * @return array
     */
    public function getSearchSymbolContract(string $symbol): array
    {
        $response = $this->httpClient->timeout(5)->get("contract-search/$symbol");

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * @param string $symbol
     * @param int $contractId
     * @return array
     */
    public function getSymbolContractChain(string $symbol, int $contractId): array
    {
        $response = $this->httpClient->timeout(30)->get("contract-chain/$symbol/$contractId");

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * @param $symbol
     * @param $contractId
     * @return float
     */
    public function getSymbolUnderlyingPrice($symbol, $contractId): float
    {
        Log::debug("IBGatewayService::getSymbolUnderlyingPrice - start", [
            'symbol' => $symbol
        ]);

        $response = $this->httpClient->retry(3, 1000)->timeout(10)->get("stock/$symbol/$contractId");

        if ($response->successful()) {
            $ibOptionContractMetaData = $response->json();
        } else {
            $ibOptionContractMetaData = ['underlyingPrice' => 0];
        }

        Log::debug("IBGatewayService::getSymbolUnderlyingPrice - end", [
            'symbol' => $symbol,
            'ibOptionContractMetaData' => $ibOptionContractMetaData
        ]);

        return round($ibOptionContractMetaData['underlyingPrice'], 1);
    }

    public function getFuturesUnderlyingPrice($futureSymbol): float
    {
        Log::debug("IBGatewayService::getFuturesUnderlyingPrice - start", [
            'futureSymbol' => $futureSymbol
        ]);

        //Fetch today's option contract for futures
        $response = $this->getOptionsContracts($futureSymbol, now(), OptionsContractsTypeEnum::CALL());

        if(count($response) > 0) {
            $currentFuturesSymbol = $response[0]['underSymbol'];
            $currentFuturesContractId = $response[0]['underConId'];

            Log::debug("IBGatewayService::getFuturesUnderlyingPrice - currentFuturesSymbol", [
                'currentFuturesSymbol' => $currentFuturesSymbol,
                'currentFuturesContractId' => $currentFuturesContractId
            ]);

            return $this->getSymbolUnderlyingPrice($currentFuturesSymbol, $currentFuturesContractId);
        } else {
            Log::info("IBGatewayService::getFuturesUnderlyingPrice - no option contract found for futures", [
                'futureSymbol' => $futureSymbol
            ]);
            return 0;
        }
    }

    /**
     * @return array
     */
    public function getListRealTimeSubscriptions(): array
    {
        $response = $this->httpClient->get("realtime-data-sub-all");

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * @param int $contractId
     * @param string $exchange
     * @return array|mixed
     */
    public function subscribeRealtimeData(int $contractId, string $exchange="CBOE"): mixed
    {
        $response = $this->httpClient->get("realtime-data-sub/$exchange/$contractId");

        if ($response->successful()) {
            Log::debug("IBGatewayService::subscribeRealtimeData - success", [
                'contractId' => $contractId,
                'exchange' => $exchange,
                'response' => $response
            ]);
            return $response->json();
        } else {
            Log::debug("IBGatewayService::subscribeRealtimeData - failed", [
                'contractId' => $contractId,
                'exchange' => $exchange,
                'response' => $response
            ]);
        }

        return [];
    }

    /**
     * @param int $contractId
     * @return array|mixed
     */
    public function unsubscribeRealtimeData(int $contractId): mixed
    {
        $response = $this->httpClient->get("realtime-data-unsub/$contractId");

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    public function syncOptionContractBySymbolAndExpiry(string $symbol, Carbon $expiryDate): array
    {
        $callOptions = $this->getOptionsContracts($symbol, $expiryDate, OptionsContractsTypeEnum::CALL);
        $putOptions = $this->getOptionsContracts($symbol, $expiryDate, OptionsContractsTypeEnum::PUT);

        $allOptions = array_merge($callOptions, $putOptions);
        $optionsDataForInsert = array_map(function(array $optionContractResponse) use ($symbol, $expiryDate) {
            return [
                'symbol' => $symbol,
                'expiry_date' => $expiryDate->format('Y-m-d'),
                'option_type' => $optionContractResponse['contract']['right'],
                'strike' => $optionContractResponse['contract']['strike'],
                'created_at' => now(),
                'updated_at' => now()
            ];
        }, $allOptions);

        return [];
    }
}
