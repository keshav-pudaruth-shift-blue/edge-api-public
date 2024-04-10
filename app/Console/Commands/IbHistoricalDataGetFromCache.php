<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class IbHistoricalDataGetFromCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ib:get-historical-data-from-cache {contractId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Fetching historical data from cache');
        $contractId = $this->argument('contractId');
        $this->info("Contract ID: $contractId");
        $ibHistoricalData = Redis::get('ib-historical-data:' . $contractId);
        if($ibHistoricalData) {
            $ibHistoricalDataParsed = json_decode($ibHistoricalData, true);
            $this->info('Historical data found');
            $this->info('Number of ticks: ' . count($ibHistoricalDataParsed['ticks']));
            if(count($ibHistoricalDataParsed['ticks']) > 0) {
                $this->info(
                    'First tick: ' . Carbon::createFromTimestamp(
                        $ibHistoricalDataParsed['ticks'][0]['time']
                    )->toDateTimeString()
                );
                $this->info(
                    'Last tick: ' . Carbon::createFromTimestamp(
                        $ibHistoricalDataParsed['ticks'][count($ibHistoricalDataParsed['ticks']) - 1]['time']
                    )->toDateTimeString()
                );
                $this->table(['Time', 'Open', 'High', 'Low', 'Close', 'Volume', 'Trade Count', 'WAP'],
                    $ibHistoricalDataParsed['ticks']);
            }
        } else {
            $this->info('No historical data found');
        }
        $this->info('Done');

        return 0;
    }
}
