<?php

namespace App\Feature\Benzinga\Jobs;

use App\Feature\Discord\Services\DiscordAlertService;
use App\Feature\Discord\Services\WebhookService;
use App\Feature\ThetaData\Services\ThetaDataAPI;
use App\Job\BasicJob;
use App\Services\BenzingaService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class AnalyzeStockAnalystTodayJob extends BasicJob implements ArtisanDispatchable
{
    public int $tries = 3;

    //Retry every 30 mins
    public int $backoff = 1800;
    /**
     * @var BenzingaService
     */
    private mixed $benzingaService;

    /**
     * @var ThetaDataAPI
     */
    private mixed $thetaDataAPI;

    /**
     * @var WebhookService
     */
    private mixed $discordWebhookService;

    public string $artisanName = 'benzinga:analyze-stock';

    public function handle(): void
    {
        $this->benzingaService = app(BenzingaService::class);
        $this->thetaDataAPI = app(ThetaDataAPI::class);
        $this->discordWebhookService = app(WebhookService::class);
        $response = $this->benzingaService->getAnalystRatings();

        if ($response->successful()) {

            $symbolGroupedAnalystRatings = $this->fetchAndFormatAnalystRatings($response);

            foreach ($symbolGroupedAnalystRatings as $tickerRow) {
                $mainDiscordPayload = [
                    'allowed_mentions' => [
                        'parse' => ['users', 'roles', 'everyone']
                    ],
                ];
                $tickerAnalystRatings = [];
                foreach ($tickerRow as $ratingRow) {
                    $title = "{$ratingRow['analyst']} " . strtolower($ratingRow['action_company']) . " {$ratingRow['name']} ({$ratingRow['ticker']}) to {$ratingRow['rating_current']}";
                    if(!empty($ratingRow['adjusted_pt_current'])) {
                        $title .= " PT {$ratingRow['adjusted_pt_current']}";
                    }
                    if($ratingRow['rating_current'] === 'Buy') {
                        $title .= " :rocket:";
                    } elseif($ratingRow['rating_current'] === 'Sell') {
                        $title .= " :axe:";
                    }

                    $tickerAnalystRatings[] =
                        [
                            'url' => 'https://www.tradingview.com/chart/?symbol=' . $ratingRow['ticker'],
                            'color' => match ($ratingRow['action_company']) {
                                'Upgrades' => 65331,
                                'Downgrades' => 16711680,
                                'Initiates Coverage On', 'Reinstates' => match ($ratingRow['rating_current']) {
                                    'Buy', 'Strong Buy', 'Market Outperform', 'Outperform', 'Overweight' => 65331,
                                    'Sell', 'Hold' => 16711680,
                                    default => 0
                                },
                                default => 0
                            },
                            'title' => $title,
                            'fields' => [
                                [
                                    'name' => 'Target Price',
                                    'value' => $ratingRow['adjusted_pt_current'],
                                    'inline' => true,
                                ],
                                [
                                    'name' => 'Stock Price',
                                    'value' => (string)$ratingRow['current_stock_price'],
                                    'inline' => true,
                                ],
                                [
                                    'name' => $ratingRow['adjusted_pt_current'] > $ratingRow['current_stock_price'] ? 'Upside' : 'Downside',
                                    'value' => !empty($ratingRow['adjusted_pt_current']) ? round((((float)$ratingRow['pt_current'] - (float)$ratingRow['current_stock_price']) / $ratingRow['current_stock_price']) * 100, 2) . '%' : 'N/A',
                                    'inline' => true,
                                ],
                                [
                                    'name' => 'Action',
                                    'value' => $ratingRow['action_company'],
                                    'inline' => true,
                                ],
                                [
                                    'name' => 'New Rating',
                                    'value' => $ratingRow['rating_current'],
                                    'inline' => true,
                                ],
                                [
                                    'name' => 'Previous Rating',
                                    'value' => $ratingRow['rating_prior'],
                                    'inline' => true,
                                ],
                                [
                                    'name' => 'Analyst Firm',
                                    'value' => $ratingRow['analyst'],
                                    'inline' => true,
                                ],
                                [
                                    'name' => 'Analyst Name',
                                    'value' => $ratingRow['analyst_name'],
                                    'inline' => true,
                                ],
                                [
                                    'name' => 'Analyst Score',
                                    'value' => Arr::get($ratingRow, 'ratings_accuracy.smart_score'),
                                    'inline' => true,
                                ],
                            ]
                        ];
                }

                $mainDiscordPayload['embeds'] = $tickerAnalystRatings;

                $this->discordWebhookService->sendRaw(config('discord.webhooks.analyst-ratings'), $mainDiscordPayload);
            }
        } else {
            Log::error('Failed to get analyst ratings from Benzinga', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
        }
    }

    private function fetchAndFormatAnalystRatings($response)
    {
        $responseData = $response->json();

        $todayAnalystRatings = collect($responseData['ratings'])->filter(function ($item) {
            return $item['date'] === now()->format('Y-m-d');
        });

        Log::info('AnalyzeStockAnalystTodayJob - Today Analyst ratings found', [
            'total' => $todayAnalystRatings->count()
        ]);

        $accurateAnalystRatings = $todayAnalystRatings->filter(function ($item) {
            return in_array($item['action_company'], ['Upgrades', 'Downgrades', 'Initiates Coverage On', 'Reinstates']) === true;
        });

        Log::info('AnalyzeStockAnalystTodayJob - Accurate Analyst ratings found', [
            'total' => $accurateAnalystRatings->count()
        ]);

        $highValueAnalystRatings = $accurateAnalystRatings->filter(function ($item) {
            return (int)Arr::get($item, 'ratings_accuracy.smart_score', 0) >= 70;
        });

        Log::info('AnalyzeStockAnalystTodayJob - High value Analyst ratings found', [
            'total' => $highValueAnalystRatings->count()
        ]);

        //Payload example
        /*
         * {
                "action_company": "Reinstates",
                "action_pt": "Announces",
                "adjusted_pt_current": "1100.00",
                "adjusted_pt_prior": "",
                "analyst": "Citigroup",
                "analyst_id": "58073fb5846faa000106abff",
                "analyst_name": "Christopher Danely",
                "currency": "USD",
                "date": "2023-12-11",
                "exchange": "NASDAQ",
                "id": "65770e51f754e50001dfb907",
                "importance": 0,
                "name": "Broadcom",
                "notes": "",
                "pt_current": "1100.0000",
                "pt_prior": "",
                "rating_current": "Buy",
                "rating_prior": "",
                "ratings_accuracy": {
                    "smart_score": "78.88"
                },
                "ticker": "AVGO",
                "time": "08:27:45",
                "updated": 1702301347,
                "url": "https://www.benzinga.com/quote/AVGO/analyst-ratings",
                "url_calendar": "https://www.benzinga.com/quote/AVGO/analyst-ratings",
                "url_news": "https://www.benzinga.com/stock-articles/AVGO/analyst-ratings"
            },
         */
        $highValueAnalystRatings = $highValueAnalystRatings->map(function ($item) {
            $item['current_stock_price'] = $this->thetaDataAPI->getStockLastEODClose($item['ticker']);

            return $item;
        });

        Log::info('AnalyzeStockAnalystTodayJob - Analyst ratings found count', [
            'total' => $todayAnalystRatings->count(),
            'accurate' => $highValueAnalystRatings->count()
        ]);

        return $highValueAnalystRatings->groupBy('ticker');
    }
}
