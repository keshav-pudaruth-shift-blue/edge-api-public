<?php

namespace App\Feature\ThetaData\Jobs;

use App\Feature\Discord\Services\DiscordAlertService;
use App\Job\BasicJob;
use \App\Feature\ThetaData\Services\ThetaDataOptionAPI;
use App\Models\OptionsChainWatchlist;
use App\Services\OPRAservice;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class AnalyzeThetadataUnusualOptionTrade extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 2;

    public $queue = 'thetadata';

    /**
     * @var ThetaDataOptionAPI
     */
    private $thetaDataOptionAPI;

    /**
     * @var OPRAservice
     */
    private $opraService;

    /**
     * @var string
     */
    public string $symbol;

    /**
     * @var Carbon
     */
    public $expiryDate;

    /**
     * @var int
     */
    public int $volumeAlertThreshold;

    /**
     * @var string
     */
    public string $scanType;

    /**
     * @var string
     */
    public string $artisanName = 'thetadata:analyze-trade';

    /**
     * @var string
     */
    private string $currentTimestampCacheKey;

    public function __construct(string $symbol, int $expiryDate, int $volumeAlertThreshold, string $scanType)
    {
        $this->symbol = $symbol;
        $this->expiryDate = Carbon::createFromFormat('Ymd', $expiryDate, 'America/New_York');
        $this->volumeAlertThreshold = $volumeAlertThreshold;
        $this->scanType = $scanType;
        $this->currentTimestampCacheKey = 'thetadata-unusual-option-trade-ms-' . $this->symbol . '-' . $scanType . '-' . $this->expiryDate->format('Ymd');
    }

    public function handle(): void
    {
        $this->thetaDataOptionAPI = app(ThetaDataOptionAPI::class);
        $this->opraService = app(OPRAservice::class);

        $response = $this->thetaDataOptionAPI->getTradeQuoteAll($this->symbol, $this->expiryDate, now(), now());
        if ($response->ok()) {
            Log::debug("AnalyzeThetadataUnusualOptionTrade - Bulk trade quotes received", [
                'symbol' => $this->symbol,
                'expiryDate' => $this->expiryDate
            ]);
            $tradeQuotesResponseJson = $response->json();
            $headers = array_flip($tradeQuotesResponseJson['header']['format']);
            $unusualSizeOptions = [];
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
                            'type' => $contractRow['contract']['right'],
                            'strike' => $contractRow['contract']['strike'] / 1000,
                            'sequence' => $tickRow[$headers['sequence']],
                            'contract_name' => $contractName,
                            'size' => $tickRow[$headers['size']],
                            'price' => $tickRow[$headers['price']],
                            'price_action' => $this->opraService->derivePriceAction($tickRow[$headers['price']], $tickRow[$headers['bid']], $tickRow[$headers['ask']]),
                            'purchase_action' => $this->opraService->derivePurchaseAction((int)$tickRow[$headers['size']], (int)$tickRow[$headers['bid_size']], (int)$tickRow[$headers['ask_size']], $tickRow[$headers['price']], $tickIndex, $contractRow['ticks']),
                            'timestamp' => $tickRow[$headers['ms_of_day']],
                            'trade_type' => $tradeCondition['type'],
                            'trade_leg' => $tradeCondition['leg'],
                            'trade_action' => $tradeCondition['action'],
                        ];
                    }
                }
            }

            //Set current sequence, or use the first sequence in the response
            $currentTimestamp = Cache::get($this->currentTimestampCacheKey, 0);

            $unusualSizeOptions = array_filter($unusualSizeOptions, function ($row) use ($currentTimestamp) {
                return $row['timestamp'] > $currentTimestamp && $row['size'] >= $this->volumeAlertThreshold && $row['trade_action'] === 'defer';
            });

            //sort $unusualSizeOptions by time
            usort($unusualSizeOptions, function ($a, $b) {
                return $a['sequence'] <=> $b['sequence'];
            });

            if (!empty($unusualSizeOptions)) {
                /**
                 * @var DiscordAlertService $discordAlertService
                 */
                $discordAlertService = app(DiscordAlertService::class);
                foreach ($unusualSizeOptions as $unusualSizeOptionRow) {
                    Log::debug("AnalyzeThetadataUnusualOptionTrade - Unusual option trade found", [
                        'symbol' => $this->symbol,
                        'expiryDate' => $this->expiryDate,
                        'unusualSizeOption' => $unusualSizeOptionRow
                    ]);
                    $unusualSizeOptionRow['symbol'] = $this->symbol;
                    $unusualSizeOptionRow['expiry'] = $this->expiryDate;
                    $unusualSizeOptionRow['premium'] = round($unusualSizeOptionRow['price'] * $unusualSizeOptionRow['size'] * 100, 2);

                    switch ($this->scanType) {
                        case OptionsChainWatchlist::WATCHLIST_TYPE_0DTE:
                        case OptionsChainWatchlist::WATCHLIST_TYPE_1DTE:
                            if(app()->environment('production') === false) {
                                Log::info("AnalyzeThetadataUnusualOptionTrade - Sending alert to discord", [
                                    'symbol' => $this->symbol,
                                    'expiryDate' => $this->expiryDate,
                                    'unusualSizeOption' => $unusualSizeOptionRow
                                ]);
                                break;
                            }

                            $alertLock = Cache::lock('thetadata-unusual-option-trade-alert-' . $this->symbol . '-' . $this->expiryDate->format('Ymd').'-'.$unusualSizeOptionRow['sequence'], 300);

                            if($alertLock->get()) {
                                $discordAlertService->sendUnusualAlert($unusualSizeOptionRow, $this->scanType);

                                //Check if lounge eligible alert
                                //Big size
                                if (($unusualSizeOptionRow['size'] >= 1000 && $unusualSizeOptionRow['price'] > 0.01) ||
                                    //Big premium
                                    ($unusualSizeOptionRow['premium'] > 1000000)
                                ) {
                                    $discordAlertService->sendUnusualLoungeAlert($unusualSizeOptionRow, $this->scanType);
                                }
                            }
                            break;
                        case OptionsChainWatchlist::WATCHLIST_TYPE_OPEX:
                            //TODO: to be implemented
                    }
                }
                //Update cache
                Cache::put($this->currentTimestampCacheKey, last($unusualSizeOptions)['timestamp'], now('America/New_York')->setHour(20));
            } else {
                Log::debug("AnalyzeThetadataUnusualOptionTrade - No unusual option trade found", [
                    'symbol' => $this->symbol,
                    'expiryDate' => $this->expiryDate,
                    'currentTimestamp' => $currentTimestamp,
                    'scanType' => $this->scanType,
                ]);
            }
        } else {
            Log::error("AnalyzeThetadataUnusualOptionTrade - Error fetching bulk trade quotes", [
                'status' => $response->status(),
                'body' => $response->body(),
                'symbol' => $this->symbol,
                'expiryDate' => $this->expiryDate,
                'scanType' => $this->scanType,
            ]);
        }
    }
}
