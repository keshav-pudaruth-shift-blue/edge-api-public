<?php

namespace App\Feature\Discord\Services;

use App\Feature\CuriousSignals\Models\CuriousSignals;
use App\Models\OptionsContracts;
use App\Models\OptionsContractsTypeEnum;
use App\Services\OPRAservice;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;

class DiscordAlertService
{
    /**
     * @var array
     */
    public array $vipAlertTypes = [
        CuriousSignals::SIGNAL_HIGH_VOLUME,
    ];


    public function __construct(private readonly WebhookService $discordWebhookService)
    {
    }

    /**
     * @param OptionsContracts $contract
     * @param CuriousSignals $alertType
     * @param array $data
     * @return Response
     * @throws \Exception
     */
    public function sendAlert(
        OptionsContracts $contract,
        CuriousSignals $alertType,
        array $data
    ): \Illuminate\Http\Client\Response {
        $webhookPayload = $this->getDefaultWebhookPayload();

        $title = $contract->strike_price . $contract->option_type . ' ' . $contract->expiry_date->format(
                'm/d'
            ) . ' ' . $contract->dte . 'dte';

        //OHLCV
        $action = 'NEUTRAL';

        if ($data['open'] > $data['close']) {
            $action = $contract->option_type === 'C' ? 'BEARISH' : 'BULLISH';
        } elseif ($data['open'] < $data['close']) {
            $action = $contract->option_type === 'C' ? 'BULLISH' : 'BEARISH';
        }

        switch ($action) {
            case 'BULLISH':
                $webhookPayload['embeds'][0]['title'] = '🟢 ' . ($contract->option_type === 'C' ? 'BTO ' : 'STC ') . $title;
                $webhookPayload['embeds'][0]['color'] = 65331;
                break;
            case 'BEARISH':
                $webhookPayload['embeds'][0]['title'] = '🔴 ' . ($contract->option_type === 'C' ? 'STC ' : 'BTO ') . $title;
                $webhookPayload['embeds'][0]['color'] = 16711680;
                break;
            case 'NEUTRAL':
                $webhookPayload['embeds'][0]['title'] = '⚪ Neutral ' . $title;
                $webhookPayload['embeds'][0]['color'] = 16777215;
                break;
        }

        //Description
        switch($alertType)
        {
            case CuriousSignals::SIGNAL_UNUSUAL_ACTIVITY:
                $webhookPayload['embeds'][0]['description'] = '🔍 Unusual Activity';
                break;
            case CuriousSignals::SIGNAL_HIGH_VOLUME:
                $webhookPayload['embeds'][0]['description'] = '🚀 High Volume';
                break;
        }

        $webhookPayload['embeds'][0]['fields'][0]['value'] = (string)$data['volume'];
        $webhookPayload['embeds'][0]['fields'][1]['value'] = (string)$data['low'];
        $webhookPayload['embeds'][0]['fields'][2]['value'] = '$' . ($data['volume'] * $data['low']) / 10 . 'K';
        //$webhookPayload['embeds'][0]['fields'][3]['value'] = (string)$data['underlying_price'];

        //Handle VIP alerts
        if (in_array($alertType, $this->vipAlertTypes)) {
            $this->discordWebhookService->send($contract->symbol, $webhookPayload, true);
        }

        //Add mentions
        switch ($contract->symbol) {
            case '^SPX':
            case 'SPX':
                $webhookPayload['embeds'][0]['description'] .= ' <@&1098550215289606196>';
                break;
            case 'SPY':
                $webhookPayload['embeds'][0]['description'] .= ' <@&1098550516310614066>';
                break;
            case 'QQQ':
                $webhookPayload['embeds'][0]['description'] .= ' <@&1098553153168875631>';
                break;
        }

        return $this->discordWebhookService->send($contract->symbol, $webhookPayload);
    }

    /**
     * @param string $symbolName
     * @param float $strike
     * @param string $type
     * @param string $expiry
     * @param $underlyingPrice
     * @return void
     * @throws \Exception
     */
    public function sendMojoAlert(string $symbolName, float $strike, string $type, string $expiry, $underlyingPrice)
    {
        $webhookPayload = $this->getDefaultMojoWebhookPayload();

        switch ($type) {
            case 'C':
                $webhookPayload['embeds'][0]['color'] = 65331;
                break;
            case 'P':
                $webhookPayload['embeds'][0]['color'] = 16711680;
                break;
        }

        $expiryDate = Carbon::createFromFormat('ymd', $expiry);

        $dte = $expiryDate->diffInDaysFiltered(function(Carbon $date) {
            return !$date->isWeekend();
        }, now());

        $webhookPayload['embeds'][0]['title'] = 'Mojo Trade Idea - ' . $symbolName . ' ' . $strike . $type . ' ' . $expiryDate->format('m/d/y'). ' ' . $dte . 'dte';
        $webhookPayload['embeds'][0]['url'] = "https://unusualwhales.com/option-chain/$symbolName$expiry$type". (str_pad(number_format($strike,3,'',''),8,'0',STR_PAD_LEFT));
        $webhookPayload['embeds'][0]['fields'][0]['value'] = $symbolName;
        $webhookPayload['embeds'][0]['fields'][1]['value'] = (string)$strike;
        $webhookPayload['embeds'][0]['fields'][2]['value'] = $expiryDate->format('m/d/y');
        $webhookPayload['embeds'][0]['fields'][3]['value'] = $type === 'C' ? 'Call' : 'Put';
        $webhookPayload['embeds'][0]['fields'][5]['value'] = (string)$dte;
        $webhookPayload['embeds'][0]['fields'][6]['value'] = (string)$underlyingPrice;
        $webhookPayload['embeds'][0]['fields'][7]['value'] = 'https://www.tradingview.com/chart/?symbol=' . $symbolName;

        $this->discordWebhookService->send('SPX', $webhookPayload,false,true);
    }

    /**
     * @param array $unusualSizeOptions
     * @param string $scanType
     * @return void
     * @throws \Exception
     */
    public function sendUnusualAlert(array $unusualSizeOptions, string $scanType): void
    {
        $discordPayload = $this->constructUnusualAlert($unusualSizeOptions);
        $this->discordWebhookService->sendRaw(config('discord.webhooks.'.$scanType.'.'.strtolower($unusualSizeOptions['symbol'])), $discordPayload);
    }

    /**
     * @param array $unusualSizeOptions
     * @param string $scanType
     * @return void
     * @throws \Exception
     */
    public function sendUnusualLoungeAlert(array $unusualSizeOptions, string $scanType): void
    {
        $discordPayload = $this->constructUnusualAlert($unusualSizeOptions);
        $this->discordWebhookService->sendRaw(config("discord.webhooks.$scanType.lounge"), $discordPayload);
    }


    /**
     * @param array $unusualSizeOptions
     * @return array
     * @throws \Exception
     */
    public function constructUnusualAlert(array $unusualSizeOptions): array
    {
        $webhookPayload = $this->getDefaultUnusualWebhookPayload();

        $symbolName = strtoupper($unusualSizeOptions['symbol']);
        $strike = $unusualSizeOptions['strike'];
        $type = $unusualSizeOptions['type'];
        $expiry = $unusualSizeOptions['expiry'];
        $size = $unusualSizeOptions['size'];
        $price = $unusualSizeOptions['price'];
        $premium = $unusualSizeOptions['premium'] < 1000000 ? round($unusualSizeOptions['premium'] / 1000, 0) : round($unusualSizeOptions['premium'] / 1000000, 1);
        $dte = $expiry->isToday() ? 0 : $expiry->diffInDaysFiltered(function(Carbon $date) {
            return !$date->isWeekend();
        }, now());
        $tradeType = $unusualSizeOptions['trade_type'];

        switch ($type) {
            case 'C':
                $webhookPayload['embeds'][0]['color'] = match ($unusualSizeOptions['purchase_action']) {
                    'Buy' => 65331,
                    'Sell' => 16711680,
                    default => 16777215,
                };
                break;
            case 'P':
                $webhookPayload['embeds'][0]['color'] = match ($unusualSizeOptions['purchase_action']) {
                    'Buy' => 16711680,
                    'Sell' => 65331,
                    default => 16777215,
                };
                break;
        }

        switch($tradeType) {
            case OPRAservice::TRADE_TYPE_NORMAL:
                $titleTradePlaceholder = 'Unusual Trade - ';
                break;
            case OPRAservice::TRADE_TYPE_SWEEP:
                $titleTradePlaceholder = 'Sweep Trade - ';
                $webhookPayload['embeds'][0]['description'] = '🧹 Sweep Trade';
                break;
            case OPRAservice::TRADE_TYPE_AUCTION:
                $titleTradePlaceholder = 'Auction Trade - ';
                $webhookPayload['embeds'][0]['description'] = '🔨 Auction Trade';
                break;
            case OPRAservice::TRADE_TYPE_FLOOR:
                $titleTradePlaceholder = 'Floor Trade - ';
                $webhookPayload['embeds'][0]['description'] = '🏛️ Floor Trade';
                break;
            default:
                $titleTradePlaceholder = 'Electronic Trade - ';
                break;
        }

        $webhookPayload['embeds'][0]['title'] = $titleTradePlaceholder . ' '. $unusualSizeOptions['purchase_action']. ' '. $symbolName . ' ' . $unusualSizeOptions['contract_name'] . ' * ' . $size . ' @ '. $price . ' ' . $dte . 'dte';
        $webhookPayload['embeds'][0]['fields'][0]['value'] = $symbolName;
        $webhookPayload['embeds'][0]['fields'][1]['value'] = (string)$strike;
        $webhookPayload['embeds'][0]['fields'][2]['value'] = $expiry->format('m/d/y');
        $webhookPayload['embeds'][0]['fields'][3]['value'] = $type === OptionsContractsTypeEnum::CALL->value ? 'Call' : 'Put';
        $webhookPayload['embeds'][0]['fields'][4]['value'] = $unusualSizeOptions['purchase_action'];
        $webhookPayload['embeds'][0]['fields'][5]['value'] = $unusualSizeOptions['price_action'];
        $webhookPayload['embeds'][0]['fields'][6]['value'] = (string)$price;
        $webhookPayload['embeds'][0]['fields'][7]['value'] = (string)$size;
        $webhookPayload['embeds'][0]['fields'][8]['value'] = '$ ' . $premium .($unusualSizeOptions['premium'] < 1000000 ? 'K' : 'M'). ($unusualSizeOptions['premium'] > 1000000 ? ' 💰' : '');
        $webhookPayload['embeds'][0]['fields'][9]['value'] = now('US/Eastern')->startOfDay()->addMilliseconds($unusualSizeOptions['timestamp'])->format('H:i:s');
        $webhookPayload['embeds'][0]['fields'][10]['value'] = $unusualSizeOptions['trade_leg'];

        return $webhookPayload;
    }


    /**
     * @param string $title
     * @param string $tweetURL
     * @param string $tweetText
     * @param string $tweetSource
     * @param string $tweetDatetime
     * @return void
     */
    public function sendTradeAlert(string $title, string $tweetURL, string $tweetText, string $tweetSource, string $tweetDatetime)
    {
        $webhookPayload = $this->getDefaultTradeWebhookPayload();

        $webhookPayload['embeds'][0]['title'] = $title;
        $webhookPayload['embeds'][0]['url'] = $tweetURL;
        $webhookPayload['embeds'][0]['fields'][2]['value'] = $tweetText;
        $webhookPayload['embeds'][0]['fields'][0]['value'] = $tweetSource;
        $webhookPayload['embeds'][0]['fields'][1]['value'] = $tweetDatetime;

        $this->discordWebhookService->send('SPX', $webhookPayload,false,false,true);
    }

    private function getDefaultTradeWebhookPayload(): array
    {
        return [
            'embeds' => [
                [
                    'title' => '',
                    'description' => 'Twitter Trade',
                    'fields' => [
                        [
                            'name' => 'Source',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Timestamp',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Original Text',
                            'value' => 'N/A',
                            'inline' => false,
                        ],
                    ]
                ]
            ],
            "allowed_mentions" => [
                'parse' => ['users', 'roles', 'everyone']
            ]
        ];
    }

    /**
     * @return array
     */
    private function getDefaultMojoWebhookPayload(): array
    {
        return [
            'embeds' => [
                [
                    'title' => '',
                    'description' => '🧞 Mojo Activity',
                    'fields' => [
                        [
                            'name' => 'Symbol',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Strike',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Expiration',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Call/Put',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Buy/Sell',
                            'value' => 'Buy',
                            'inline' => true,
                        ],
                        [
                            'name' => 'DTE',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Underlying symbol price',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Stock chart',
                            'value' => 'N/A',
                        ],
                    ]
                ]
            ],
            "allowed_mentions" => [
                'parse' => ['users', 'roles', 'everyone']
            ]
        ];
    }

    /**
     * @return array
     */
    private function getDefaultUnusualWebhookPayload(): array
    {
        return [
            'embeds' => [
                [
                    'title' => '',
                    'description' => '🔍 Unusual Activity',
                    'fields' => [
                        [
                            'name' => 'Symbol',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Strike',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Expiration',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Call/Put',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Buy/Sell',
                            'value' => 'Buy',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Price Action',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Price',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Size',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Prems Spent',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Timestamp(ET)',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Trade Leg',
                            'value' => 'N/A',
                            'inline' => true,
                        ],
                    ]
                ]
            ],
            "allowed_mentions" => [
                'parse' => ['users', 'roles', 'everyone']
            ]
        ];
    }

    /**
     * @return array
     */
    private function getDefaultWebhookPayload(): array
    {
        return [
            'embeds' => [
                [
                    'title' => '',
                    'description' => '🔍 Unusual Activity',
                    'fields' => [
                        [
                            'name' => 'Size',
                            'value' => '',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Price',
                            'value' => '',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Premium Paid',
                            'value' => '',
                        ],
                        [
                            'name' => 'Underlying price',
                            'value' => 'N/A',
                        ],
                    ]
                ]
            ],
            "allowed_mentions" => [
                'parse' => ['users', 'roles', 'everyone']
            ]
        ];
    }
}
