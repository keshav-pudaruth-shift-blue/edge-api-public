<?php

namespace App\Repositories;

use App\Models\OptionsContractsHistoricalData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OptionsContractsHistoricalDataRepository extends BaseRepository
{
    private $realTimeDataCacheKey = 'ib-historical-data:';

    public function __construct(protected OptionsContractsHistoricalData $model)
    {
    }

    /**
     * @param int $contractId
     * @return OptionsContractsHistoricalData|null
     */
    public function getLatestHistoricalData(int $contractId): ?OptionsContractsHistoricalData
    {
        return $this->model->where('options_contracts_id', $contractId)->orderBy('datetime', 'desc')->with(
            'optionContract.tradingHours'
        )->first();
    }

    /**
     * @param int $contractId
     * @return array|null
     */
    public function getRealTimeDataByContractId(int $contractId): ?array
    {
        if(app()->runningUnitTests()) {
            $result = Cache::get($this->realTimeDataCacheKey . $contractId);
        } else {
            $result = Redis::get($this->realTimeDataCacheKey . $contractId);
            if($result === null) {
                $result = [];
            } else {
                $result = json_decode($result, true);
            }
        }

        return $result;
    }

    /**
     * @param int $contractId
     * @return bool
     */
    public function deleteRealTimeDataByContractId(int $contractId): bool
    {
        return app()->runningUnitTests() ? Cache::forget($this->realTimeDataCacheKey . $contractId) : Redis::forget(
            $this->realTimeDataCacheKey . $contractId
        );
    }

    /**
     * @param int $contractId
     * @param int $threshold
     * @return bool
     */
    public function checkLivenessRealTimeDataByContractId(int $contractId, int $threshold = 60): bool
    {
        $realTimeData = $this->getRealTimeDataByContractId($contractId);
        Log::debug(
            'OptionsContractsHistoricalDataRepository::checkLivenessRealTimeDataByContractId - Real time data: ',
            [
                $realTimeData
            ]
        );
        if (!empty($realTimeData)) {
            $lastTick = last($realTimeData['ticks']);
            if (!empty($lastTick)) {
                $lastTickTime = Carbon::createFromTimestampUTC($lastTick['time']);
                $now = Carbon::now();
                $diff = $now->diffInSeconds($lastTickTime);
                return $diff < $threshold;
            }
        }
        return false;
    }
}
