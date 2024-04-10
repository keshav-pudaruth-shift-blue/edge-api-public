<?php

namespace App\Console;

use App\Feature\CuriousSignals\Jobs\RunCuriousSignalScannerJob;
use App\Feature\IBOptionsDataSync\Console\Commands\RestartRealTimeCommand;
use App\Feature\IBOptionsDataSync\Console\Commands\SyncSymbolContractId;
use App\Feature\IBOptionsDataSync\Console\Commands\SyncSymbolOptionsContractsChainCommand;
use App\Feature\IBOptionsDataSync\Jobs\KeepIBGatewayAlive\KeepIBGatewayAliveJob;
use App\Feature\IBOptionsDataSync\Jobs\SubSymbolAllOptionsContractsRealtimeDataJob;
use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolRealTimeDataPerTradingHoursJob;
use App\Feature\OptionsDataSync\Command\OptionsDataManualSync;
use App\Feature\OptionsDataSync\Jobs\CleanupExpiredDataJob;
use App\Feature\OptionsDataSync\Jobs\CleanupStaleDataJob;
use App\Feature\SymbolSync\Job\CBOESymbolSync;
use App\Feature\SystemSymbolWatchlist\Jobs\SyncSystemSymbolWatchlistOptionsDataJob;
use App\Feature\ThetaData\Command\ThetaDataGetUnusualTradeCommand;
use App\Feature\ThetaData\Jobs\QueueThetadataUnusualOptionTrades0dte;
use App\Feature\ThetaData\Jobs\QueueThetadataUnusualOptionTrades1dte;
use App\Feature\TOSAlerts\Jobs\PollEmailInboxForAlertsJob;
use App\Feature\Twitter\Jobs\RetrieveTweets;
use App\Repositories\HolidayRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        OptionsDataManualSync::class,
        SyncSymbolContractId::class,
        SyncSymbolOptionsContractsChainCommand::class,
        RestartRealTimeCommand::class,
        \Webklex\IMAP\Commands\ImapIdleCommand::class,
        ThetaDataGetUnusualTradeCommand::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule
            ->command('telescope:prune --hours=36')
            ->daily()
            ->name('telescopePrune')
            ->onOneServer()
            ->runInBackground();

        $this->productionSchedule($schedule);
    }

    protected function productionSchedule(Schedule $schedule): void
    {
        if($this->app->environment('production')) {

            $schedule->job(new PollEmailInboxForAlertsJob())
                ->name('MojoPollEmailInboxForAlertsJob')
                ->timezone('America/New_York')
                ->weekdays()
                ->everyFiveMinutes()
                ->between('09:30', '16:00')
                ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
                ->onOneServer();

            //PreMarket
            $schedule->job(new PollEmailInboxForAlertsJob())
                ->name('MojoPollEmailInboxForAlertsJob')
                ->timezone('America/New_York')
                ->weekdays()
                ->at('08:00')
                ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
                ->onOneServer();

            //Benzinga - Fetch analyst ratings
            $schedule->job(new \App\Feature\Benzinga\Jobs\AnalyzeStockAnalystTodayJob())
                ->name('AnalyzeStockAnalystTodayJob')
                ->timezone('America/New_York')
                ->weekdays()
                ->at('08:00')
                ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
                ->onOneServer();

            //Retrives all options contracts chain for a symbol
//            $schedule->command('ib:sync-symbol-options-contracts-chain --all')
//                ->name('SyncSymbolOptionsContractsChainCommand')
//                ->timezone('America/New_York')
//                ->weekdays()
//                ->at('12:00') //Noon
//                ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
//                ->onOneServer();

            //Scan tweets for trades intraday
            $schedule->job(new RetrieveTweets(), 'retrieve-tweets', 'redis')
                ->name('RetrieveTweetsJob')
                ->timezone('America/New_York')
                ->weekdays()
                ->everyMinute()
                ->between('09:30', '16:00')
                ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
                ->onOneServer();

            //Scan tweets for trades after hours
            $schedule->job(new RetrieveTweets(), 'retrieve-tweets', 'redis')
                ->name('RetrieveTweetsJob')
                ->timezone('America/New_York')
                ->weekdays()
                ->everyFifteenMinutes()
                ->between('16:01', '18:00')
                ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
                ->onOneServer();

            //Scan tweets for trades premarket EU
            $schedule->job(new RetrieveTweets(), 'retrieve-tweets', 'redis')
                ->name('RetrieveTweetsJob')
                ->timezone('America/New_York')
                ->weekdays()
                ->everyTenMinutes()
                ->between('03:00', '09:29')
                ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
                ->onOneServer();

            $schedule->command('passport:purge')->daily();

            $this->runCBOEOptionGreeksSync($schedule);

            $this->runEarningsWatcher($schedule);

            //$this->runRealTimeOptionChainScanner($schedule);

            $this->runThetaDataScanner($schedule);
        }
    }

    private function runRealTimeOptionChainScanner(Schedule $schedule)
    {
        $schedule->job(new KeepIBGatewayAliveJob())
            ->name('KeepIBGatewayAliveJob')
            ->weekdays()
            ->everyFifteenMinutes()
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();

        $schedule->job(new RunCuriousSignalScannerJob())
            ->name('RunCuriousSignalScannerJob')
            ->timezone('America/New_York')
            ->weekdays()
            ->everyMinute()
            ->onOneServer();

        //Sync up real time feeds for contracts based on their trading schedule
        $schedule->job(new SyncSymbolRealTimeDataPerTradingHoursJob())
            ->name('SyncSymbolRealTimeDataPerTradingHoursJob')
            ->timezone('America/New_York')
            ->weekdays()
            ->everyFifteenMinutes()
            ->onOneServer();

        $schedule->command('ib:rs')
            ->name('RestartIbGateway')
            ->timezone('America/New_York')
            ->weekdays()
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->at('21:00')
            ->onOneServer();
    }

    private function runThetaDataScanner(Schedule $schedule): void
    {
        $schedule->job(new QueueThetadataUnusualOptionTrades0dte(),'thetadata', 'redis')
            ->name('QueueThetadataUnusualOptionTrades0dte')
            ->timezone('America/New_York')
            ->weekdays()
            ->everyTwoMinutes()
            ->between('09:30', '16:00')
            ->onOneServer();

        $schedule->job(new QueueThetadataUnusualOptionTrades1dte(),'thetadata', 'redis')
            ->name('QueueThetadataUnusualOptionTrades1dte')
            ->timezone('America/New_York')
            ->weekdays()
            ->everyFifteenMinutes()
            ->between('09:30', '16:00')
            ->onOneServer();
    }

    private function runEarningsWatcher(Schedule $schedule)
    {
        //Fetch earnings for the future
        $schedule->job(new \App\Feature\EarningsWatcher\Jobs\SyncEarningsToDatabase(now()->addWeeks(3), now()->addWeeks(4)))
            ->name('SyncEarningsToDatabase')
            ->timezone('America/New_York')
            ->saturdays()
            ->onOneServer();

        //Sync earnings data after market close
        $schedule
            ->call(function() {
                \App\Feature\EarningsWatcher\Jobs\SyncEarningsStockPrice::withChain([
                    new \App\Feature\EarningsWatcher\Jobs\SyncEarningsUnusualTrades()
                ])->onQueue('thetadata')->dispatch();
            })
            ->name('SyncEarningsEOD')
            ->timezone('America/New_York')
            ->weekdays()
            ->at('16:31')
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();

        //Sync earnings unusual trades during market hours
        $schedule->job(new \App\Feature\EarningsWatcher\Jobs\SyncEarningsUnusualTradesRush())
            ->name('SyncEarningsUnusualTradesRush')
            ->timezone('America/New_York')
            ->weekdays()
            ->everyFifteenMinutes()
            ->between('09:30', '16:00')
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();
    }

    private function runCBOEOptionGreeksSync(Schedule $schedule)
    {
        $schedule->job(new CBOESymbolSync())
            ->name('CBOESymbolSync')
            ->timezone('America/New_York')
            ->dailyAt('16:30')
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();

        //Market open
        $schedule->job(new SyncSystemSymbolWatchlistOptionsDataJob(), 'options-data-sync', 'redis')
            ->name('SyncSystemSymbolWatchlistOptionsDataJob')
            ->timezone('America/New_York')
            ->weekdays()
            ->at('09:50')
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();

        //Hourly sync
        $schedule->job(new SyncSystemSymbolWatchlistOptionsDataJob(), 'options-data-sync', 'redis')
            ->name('SyncSystemSymbolWatchlistOptionsDataJob')
            ->timezone('America/New_York')
            ->weekdays()
            ->hourly()
            ->between('11:00', '15:00')
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();

        //Market close
        $schedule->job(new SyncSystemSymbolWatchlistOptionsDataJob(), 'options-data-sync', 'redis')
            ->name('SyncSystemSymbolWatchlistOptionsDataJob')
            ->timezone('America/New_York')
            ->weekdays()
            ->at('16:30')
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();

        $schedule->call(function() {
            CleanupExpiredDataJob::withChain([
                new CleanupStaleDataJob()
            ])->onQueue('tortoise')->onConnection('redis-tortoise')->dispatch();
        })
            ->name('CleanupExpiredDataJob')
            ->timezone('UTC')
            ->dailyAt('01:00')
            ->when(app(HolidayRepository::class)->isTodayHoliday() === false)
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
