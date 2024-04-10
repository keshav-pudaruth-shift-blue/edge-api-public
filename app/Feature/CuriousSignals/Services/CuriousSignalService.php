<?php

namespace App\Feature\CuriousSignals\Services;

use App\Feature\CuriousSignals\Models\CuriousSignalInterval;
use App\Services\StatisticsService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use MathPHP\Exception\BadDataException;
use MathPHP\Exception\BadParameterException;
use MathPHP\Exception\OutOfBoundsException;
use MathPHP\Statistics\Outlier;


class CuriousSignalService
{
    public function __construct(
        private StatisticsService $statisticsService
    ) {}

    /**
     * @param $tickData
     * @param Carbon $fromTime
     * @param Carbon $toTime
     * @param CuriousSignalInterval $intervalCheck
     * @param $underlyingPrice
     * @return array[]
     */
    public function handle(
        $tickData,
        Carbon $fromTime,
        Carbon $toTime,
        CuriousSignalInterval $intervalCheck = CuriousSignalInterval::OneMinute,
        $underlyingPrice = null
    ): array {
        //Filter tick data by from and to time
        $fromTimeTimestamp = $fromTime->getTimestamp();
        $toTimeTimestamp = $toTime->getTimestamp();

        $filteredTickData = collect($tickData)->filter(function($tick) use ($fromTimeTimestamp, $toTimeTimestamp) {
            return $tick['time'] >= $fromTimeTimestamp && $tick['time'] <= $toTimeTimestamp;
        });

        //Regroup tick data by interval
        $groupedTickData = [];
        $finalResult = [
            'grubbs' => [],
            'shapiro-wilk' => []
        ];
        $cursor = 0;

        foreach($filteredTickData as $tick) {
            if(!isset($groupedTickData[$cursor])){
                $tick['volume'] = Arr::get($tick, 'volume', 0);
                $tick['count'] = Arr::get($tick, 'count', 0);
                $groupedTickData[$cursor] = $tick;
            } else {
                $groupedTickData[$cursor]['high'] = max($groupedTickData[$cursor]['high'], $tick['high']);
                $groupedTickData[$cursor]['low'] = min($groupedTickData[$cursor]['low'], $tick['low']);
                $groupedTickData[$cursor]['close'] = $tick['close'];
                $groupedTickData[$cursor]['volume'] += Arr::get($tick, 'volume', 0);
                $groupedTickData[$cursor]['count'] += Arr::get($tick, 'count', 0);
            }

            if($tick['time'] % $intervalCheck->value === 0) {
                $cursor++;
            }
        }

        //Calculate signals
        $groupedVolumeData = array_map(function($tick) {
            return $tick['volume'];
        }, $groupedTickData);
        $shapiroResult = $this->statisticsService->getShapiroWilk($groupedVolumeData);

        $shapiroResultPValue = sprintf("%.10f",Arr::get($shapiroResult, 'p', '1.0'));

        Log::debug('Shapiro-Wilk test result', [
            'shapiro-wilk' => $shapiroResult,
            'p-value-formatted' => $shapiroResultPValue
        ]);

        if($shapiroResult['p'] !== null && bccomp('0.05', $shapiroResultPValue, 5) === 1) {
            $grubbsResult = $this->calculateGrubbsTest($groupedVolumeData);

            Log::debug('Grubbs test result', [
                'grubbs' => $grubbsResult
            ]);

            if (isset($grubbsResult['outlier_index'])) {
                $finalResult['grubbs'] = $groupedTickData[$grubbsResult['outlier_index']];
            }

            $finalResult['shapiro-wilk'] = $shapiroResult;
        } else {
            Log::debug('Shapiro-Wilk test failed');
        }
        return $finalResult;
    }

    //use Grubbs' test, also called the ESD method (extreme studentized deviate),
    //https://www.graphpad.com/quickcalcs/grubbs1/
    /**
     * @throws BadParameterException
     * @throws OutOfBoundsException
     * @throws BadDataException
     */
    private function calculateGrubbsTest(array $data): array
    {
        try {
            $grubbsStatistics = Outlier::grubbsStatistic($data, Outlier::ONE_SIDED_UPPER);
            $criticalValue = Outlier::grubbsCriticalValue(0.05, count($data), Outlier::ONE_SIDED);

            if($grubbsStatistics > $criticalValue) {
                $highestValue = max($data);
                $highestValueKey = array_search($highestValue, $data);

                return [
                    'outlier' => $highestValue,
                    'outlier_index' => $highestValueKey,
                ];
            } else {
                Log::debug('Grubbs test failed', [
                    'grubbs-statistics' => $grubbsStatistics,
                    'critical-value' => $criticalValue
                ]);
            }
        } catch (BadParameterException|\DivisionByZeroError|BadDataException $e) {
            Log::debug('Grubbs test failed', [
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }


    //use Tukey's test, also called the T method (Tukey's test for outliers)
    //https://www.graphpad.com/quickcalcs/tukey1/

    //use the Z method (Z-score)
    //https://www.graphpad.com/quickcalcs/zscore1/


}
