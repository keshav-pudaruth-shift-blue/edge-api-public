<?php

namespace App\Feature\EarningsWatcher\Jobs;

use App\Feature\ThetaData\Services\ThetaDataAPI;
use App\Feature\ThetaData\Services\ThetaDataOptionAPI;
use App\Job\BasicJob;
use App\Models\EarningsWatcherList;
use App\Repositories\EarningsWatcherListRepository;
use App\Repositories\EarningsWatcherUnusualTradesRepository;
use App\Services\OPRAservice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class SyncEarningsUnusualTrades extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 3;

    public int $backoff = 300;

    public $queue = 'thetadata';

    public string $artisanName = 'earnings:sync-unusual-trades';

    /**
     * @var EarningsWatcherListRepository
     */
    protected mixed $earningsWatcherListRepository;

    /**
     * @var OPRAservice
     */
    private mixed $opraService;

    /**
     * @var ThetaDataOptionAPI
     */
    private mixed $thetaDataOptionAPI;

    /**
     * @var EarningsWatcherUnusualTradesRepository
     */
    private mixed $earningsWatcherUnusualTradesRepository;

    public string $cacheLockPrefix = 'earnings_watcher_unusual_trades_';

    public int $symbolCacheLockDuration = 3600;

    public int $unusualTradeCacheLockDuration = 3600; //1 hour

    public function handle(): int
    {
        $this->createServices();

        $pendingEarnings = $this->getPendingEarnings();

        if($pendingEarnings->isEmpty()) {
            Log::info("SyncEarningsUnusualTrades::handle - No pending earnings found");
            return 0;
        }

        $pendingEarnings->each(function($pendingEarning) {
            $symbollock = Cache::lock($this->cacheLockPrefix . $pendingEarning->symbol . '_' .$pendingEarning->expiry->toDateString(), $this->symbolCacheLockDuration);
            if(!$symbollock->get()) {
                Log::info("SyncEarningsUnusualTrades::handle - Unable to acquire lock for symbol",[
                    'symbol' => $pendingEarning->symbol,
                    'expiry' => $pendingEarning->date->toDateString(),
                ]);
                return;
            }

            try {
                $expirations = $this->getAndFilterExpirations($pendingEarning);
                foreach ($expirations as $expirationRow) {
                    //Fetch unusual trades
                    Log::info("SyncEarningsUnusualTrades::handle - Fetching unusual trades", [
                        'symbol' => $pendingEarning->symbol,
                        'expiration' => $expirationRow->toDateString(),
                    ]);
                    $unusualTrades = $this->getUnusualTrades($pendingEarning, $expirationRow, now()->subDay(), now());

                    //Save unusual trades
                    if (!empty($unusualTrades)) {
                        foreach ($unusualTrades as $unusualTradeRow) {
                            $lock = Cache::lock('earnings_watcher_unusual_trades_' . $unusualTradeRow['symbol'] . '_' . $unusualTradeRow['expiry'] . '_' . $unusualTradeRow['sequence'], $this->unusualTradeCacheLockDuration);
                            if ($lock->get()) {
                                $this->earningsWatcherUnusualTradesRepository->getModel()->fill($unusualTradeRow)->save();
                            }
                        }
                    } else {
                        Log::info("SyncEarningsUnusualTrades::handle - No unusual trades found", [
                            'symbol' => $pendingEarning->symbol,
                            'expiration' => $expirationRow->toDateString(),
                        ]);
                    }

                }
            } catch (\Exception $e) {
                Log::error("SyncEarningsUnusualTrades::handle - Error fetching unusual trades", [
                    'symbol' => $pendingEarning->symbol,
                    'date' => $pendingEarning->date,
                    'error' => $e->getMessage(),
                ]);
                $symbollock->release();
            }
        });

        return 0;
    }

    protected function getPendingEarnings()
    {
        return $this->earningsWatcherListRepository->getPendingEarningsWithValidStockPrice(10.0);
    }

    private function createServices(): void
    {
        $this->earningsWatcherListRepository = app(EarningsWatcherListRepository::class);
        $this->earningsWatcherUnusualTradesRepository = app(EarningsWatcherUnusualTradesRepository::class);
        $this->thetaDataOptionAPI = app(ThetaDataOptionAPI::class);
        $this->opraService = app(OPRAservice::class);
    }

    private function getAndFilterExpirations(EarningsWatcherList $pendingEarning): array
    {
        //Get Expirations
        $expirations = $this->thetaDataOptionAPI->getExpirations($pendingEarning->symbol)->json('response');

        $validExpirations = collect($expirations)->map(function($expirationRow) {
            return Carbon::createFromFormat('Ymd',$expirationRow);
        })->filter(function($expiration) use ($pendingEarning) {
            return $expiration->startOfDay()->greaterThanOrEqualTo($pendingEarning->date);
        });

        if($validExpirations->isEmpty()) {
            Log::info("SyncEarningsUnusualTrades::getAndFilterExpirations - No valid expirations found",[
                'symbol' => $pendingEarning->symbol,
                'date' => $pendingEarning->date,
            ]);
            return [];
        } else {
            $weeklyExpiration = $validExpirations->first();
            $opexExpiration = $validExpirations->filter(function ($expiration) {
                //3rd friday of every month
                return $expiration->day >= 15 && $expiration->day <= 21;
            });

            if ($opexExpiration->first()->eq($weeklyExpiration)) {
                $opexExpiration = $opexExpiration->skip(1)->first();
            }

            return [
                'weekly' => $weeklyExpiration,
                'opex' => $opexExpiration,
            ];
        }
    }

    private function getUnusualTrades(EarningsWatcherList $earningsWatcherListRow, Carbon $expiryDate, Carbon $startDate, Carbon $endDate): array
    {
        $response = $this->thetaDataOptionAPI->getTradeQuoteAll($earningsWatcherListRow->symbol, $expiryDate,  $startDate,  $endDate);
        if($response->ok()) {
            $tradeQuotesResponseJson = $response->json();
            $headers = array_flip($tradeQuotesResponseJson['header']['format']);
        } else {
            Log::error("SyncEarningsUnusualTrades::getUnusualTrades - Error fetching bulk trade quotes", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        }

        foreach ($tradeQuotesResponseJson['response'] as $contractRow) {
            $contractName = $contractRow['contract']['strike'] / 1000 . $contractRow['contract']['right'];
            foreach ($contractRow['ticks'] as $tickIndex => $tickRow) {
                // ["ms_of_day","sequence","size","condition","price","ms_of_day2","bid_size","bid","bid_exchange","ask_size","ask","ask_exchange","date"]
                $id = $tickRow[$headers['ms_of_day']] . '-' . $contractName . '-' . $tickRow[$headers['price']] . '-' . $tickRow[$headers['condition']];
                if (isset($unusualSizeOptions[$id])) {
                    $unusualSizeOptions[$id]['size'] += $tickRow[$headers['size']];
                } else {
                    $tradeCondition = $this->opraService->parseOptionTradeCondition($tickRow[$headers['condition']]);
                    $unusualSizeOptions[$id] = [
                        'symbol' => $earningsWatcherListRow->symbol,
                        'expiry' => $expiryDate,
                        'timestamp' => $tickRow[$headers['ms_of_day']],
                        'time' => Carbon::createFromFormat('Ymd', $tickRow[$headers['date']])->startOfDay()->addMilliseconds($tickRow[$headers['ms_of_day']])->toDateTimeString(),
                        'type' => $contractRow['contract']['right'],
                        'strike' => $contractRow['contract']['strike'] / 1000,
                        'sequence' => $tickRow[$headers['sequence']],
                        'contract_name' => $contractName,
                        'size' => $tickRow[$headers['size']],
                        'price' => $tickRow[$headers['price']],
                        'premium' => $tickRow[$headers['size']] * $tickRow[$headers['price']] * 100,
                        'price_action' => $this->opraService->derivePriceAction($tickRow[$headers['price']], $tickRow[$headers['bid']], $tickRow[$headers['ask']]),
                        'purchase_action' => $this->opraService->derivePurchaseAction((int)$tickRow[$headers['size']], (int)$tickRow[$headers['bid_size']], (int)$tickRow[$headers['ask_size']], $tickRow[$headers['price']], $tickIndex, $contractRow['ticks']),
                        'trade_type' => $tradeCondition['type'],
                        'trade_leg' => $tradeCondition['leg'],
                        'trade_action' => $tradeCondition['action'],
                    ];
                    $unusualSizeOptions[$id]['executed_at'] = $unusualSizeOptions[$id]['time'];
                    $unusualSizeOptions[$id]['earnings_watcher_list_id'] = $earningsWatcherListRow->id;
                }
            }
        }

        //sort $unusualSizeOptions by time
        usort($unusualSizeOptions, function($a, $b) {
            return $a['sequence'] <=> $b['sequence'];
        });

        $unusualSizeOptions = array_filter($unusualSizeOptions, function($row) {
            return $row['size'] >= 500 || $row['premium'] >= 1000000;
        });

        return $unusualSizeOptions;
    }
}
