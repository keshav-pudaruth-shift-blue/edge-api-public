<?php

namespace App\Feature\EarningsWatcher\Jobs;

use App\Feature\ThetaData\Services\ThetaDataAPI;
use App\Job\BasicJob;
use App\Repositories\EarningsWatcherListRepository;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Http;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncEarningsToDatabase extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 3;

    public int $backoff = 300;

    public string $artisanName = 'earnings:sync-dates';


    /**
     * @var EarningsWatcherListRepository
     */
    private mixed $earningsWatcherListRepository;

    /**
     * @var Carbon
     */
    private $startDate;

    /**
     * @var Carbon
     */
    private $endDate;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = Carbon::createFromFormat('Y-m-d', $startDate);
        $this->endDate = Carbon::createFromFormat('Y-m-d', $endDate);
    }


    public function handle(): void
    {
        $this->createServices();

        $dateRange = CarbonPeriod::create($this->startDate, $this->endDate);

        $earningsData = $this->getEarningsData($this->startDate, $this->endDate);

        $earningsDataList = [];

        foreach ($dateRange as $date) {
            if(!isset($earningsData['earnings'][$date->format('Y-m-d')])) {
                continue;
            }

            $earningsDataRow = $earningsData['earnings'][$date->format('Y-m-d')];

            foreach($earningsDataRow['stocks'] as $earningsStockRow) {
                $earnings = [];
                $earnings['company'] = $earningsStockRow['title'];
                $earnings['date'] = $earningsStockRow['date'];
                $earnings['time'] = $earningsStockRow['time'];
                $earnings['importance'] = $earningsStockRow['importance'];
                $earnings['symbol'] = $earningsStockRow['symbol'];
                $earnings['created_at'] = now();
                $earnings['updated_at'] = now();

                $earningsDataList[] = $earnings;
            }
        }

        //Bulk insert or update

        $this->earningsWatcherListRepository->getQuery()->insertOrIgnore($earningsDataList);
    }

    private function getEarningsData(Carbon $startDate, Carbon $endDate)
    {
        $url = "https://api.stocktwits.com/api/2/discover/earnings_calendar?date_from=".$startDate->format('Y-m-d')."&date_to=".$endDate->format('Y-m-d');
        $response = Http::withOptions(['http_errors' => true])->get($url);
        return $response->json();
    }

    private function createServices()
    {
        $this->earningsWatcherListRepository = app(EarningsWatcherListRepository::class);
    }
}
