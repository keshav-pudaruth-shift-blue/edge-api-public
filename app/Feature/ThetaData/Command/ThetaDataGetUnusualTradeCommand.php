<?php

namespace App\Feature\ThetaData\Command;

use App\Feature\ThetaData\Services\ThetaDataOptionAPI;
use App\Services\OPRAservice;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ThetaDataGetUnusualTradeCommand extends Command
{
    protected $signature = 'thetadata:get-unusual-trade {symbol} {--expiry=} {--range=}';

    protected $description = 'Get unusual trade from ThetaData';
    /**
     * @var ThetaDataOptionAPI
     */
    private mixed $thetaDataOptionAPI;
    /**
     * @var OPRAservice
     */
    private mixed $opraService;

    public function handle(): bool
    {
        $this->thetaDataOptionAPI = app(ThetaDataOptionAPI::class);
        $this->opraService = app(OPRAservice::class);

        $symbol = $this->argument('symbol');
        $this->info("Getting unusual trade for $symbol");

        $expiryDate = $this->option('expiry') ? Carbon::createFromFormat('Y-m-d',$this->option('expiry')) : now();
        $range = $this->option('range') ? Carbon::createFromFormat('Y-m-d',$this->option('range')) : now();

        $this->info("Expiry date: $expiryDate");

        $this->info("Fetching bulk trade quotes - this may take a while");

        $response = $this->thetaDataOptionAPI->getTradeQuoteAll($symbol, $expiryDate,  $range->subDay(),  $range);
        if($response->ok()) {
            $tradeQuotesResponseJson = $response->json();
            $headers = array_flip($tradeQuotesResponseJson['header']['format']);
        } else {
            $this->error("Error fetching bulk trade quotes");
            $this->error($response->status().' :'.$response->body());
            return false;
        }

        $this->info("Fetching bulk trade quotes - done");
        $this->info("Parsing bulk trade quotes");

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
                        'timestamp' => $tickRow[$headers['ms_of_day']],
                        'time' => Carbon::createFromFormat('Ymd', $tickRow[$headers['date']])->startOfDay()->addMilliseconds($tickRow[$headers['ms_of_day']])->toDateTimeString(),
                        'contract_name' => $contractName,
                        'premium' => $tickRow[$headers['size']] * $tickRow[$headers['price']] * 100,
                        'size' => $tickRow[$headers['size']],
                        'price' => $tickRow[$headers['price']],
                        'price_action' => $this->opraService->derivePriceAction($tickRow[$headers['price']], $tickRow[$headers['bid']], $tickRow[$headers['ask']]),
                        'purchase_action' => $this->opraService->derivePurchaseAction((int)$tickRow[$headers['size']], (int)$tickRow[$headers['bid_size']], (int)$tickRow[$headers['ask_size']], $tickRow[$headers['price']], $tickIndex, $contractRow['ticks']),
                        'trade_type' => $tradeCondition['type'],
                        'trade_leg' => $tradeCondition['leg'],
                        'trade_action' => $tradeCondition['action'],
                    ];
                }
            }
        }

        //sort $unusualSizeOptions by time
        usort($unusualSizeOptions, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        $unusualSizeOptions = array_filter($unusualSizeOptions, function($row) {
            return $row['size'] >= 500 || $row['premium'] >= 1000000;
        });

        $this->table(['Ms', 'Time', 'Contract', 'Premium', 'Size', 'Price','PriceAction', 'Purchase Action', 'Trade Type', 'Trade Leg', 'Trade Action'], $unusualSizeOptions);

        return true;
    }
}
