<?php

namespace App\Feature\OptionsDataSync\Tests\Services;

use App\Feature\OptionsDataSync\Services\OptionsDataSyncService;
use App\Models\OptionsContractsGreeks;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OptionsDataSyncServiceTest extends TestCase
{
    /**
     * @var OptionsDataSyncService
     */
    private $optionsDataSyncService;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @throws \Exception
     */
    public function test_sync_options_data_to_database_must_skip_when_response_is_empty_array()
    {
        $symbol = 'QQQ';
        $url = config('ib-gateway.base_uri').'og/greeks/'.$symbol;
        Http::fake([
            $url => Http::response($this->returnTestOptionData())
        ]);

        $this->optionsDataSyncService = app(OptionsDataSyncService::class);

        $this->optionsDataSyncService->syncOptionsDataToDatabase($symbol);

        $this->assertDatabaseHas((new OptionsContractsGreeks())->getTable(),[
            'symbol' => $symbol
        ]);
    }

    public function returnTestOptionData(): bool|string
    {
        return
            '{
                "timestamp": "18:47:30",
                "symbol": "QQQ",
                "data": {
                    "symbol": "QQQ",
                    "security_type": "stock",
                    "exchange_id": 2,
                    "current_price": 336.46,
                    "price_change": -0.76,
                    "price_change_percent": -0.2258,
                    "bid": 336.46,
                    "ask": 336.47,
                    "bid_size": 1,
                    "ask_size": 1,
                    "open": 337.49,
                    "high": 338.205,
                    "low": 335.43,
                    "close": 336.51,
                    "prev_day_close": 336.51,
                    "volume": 61554926,
                    "iv30": 16.568,
                    "iv30_change": 0,
                    "iv30_change_percent": 0,
                    "seqno": 0,
                    "last_trade_time": "2023-05-19T15:59:59",
                    "tick": "down",
                    "company_name": "PowerShares QQQ Trust, Series 1 (ETF)",
                    "options": [
                        {
                            "option": "QQQ230522C00260000",
                            "bid": 76.08,
                            "bid_size": 3,
                            "ask": 76.3,
                            "ask_size": 3,
                            "iv": 1.0682,
                            "open_interest": 0,
                            "volume": 0,
                            "delta": 0.9995,
                            "gamma": 0,
                            "theta": 0,
                            "rho": 0.0219,
                            "vega": 0.0003,
                            "theo": 76.1569,
                            "change": 0,
                            "open": 0,
                            "high": 0,
                            "low": 0,
                            "tick": "no_change",
                            "last_trade_price": 0,
                            "last_trade_time": null,
                            "percent_change": 0,
                            "prev_day_close": 76.8600006103516
                        },
                        {
                            "option": "QQQ230522P00260000",
                            "bid": 0,
                            "bid_size": 0,
                            "ask": 0.01,
                            "ask_size": 193,
                            "iv": 0.8992,
                            "open_interest": 121,
                            "volume": 0,
                            "delta": -0.0002,
                            "gamma": 0,
                            "theta": -0.0012,
                            "rho": 0,
                            "vega": 0.0003,
                            "theo": 0.0013,
                            "change": 0,
                            "open": 0,
                            "high": 0,
                            "low": 0,
                            "tick": "up",
                            "last_trade_price": 0.02,
                            "last_trade_time": "2023-05-18T15:37:13",
                            "percent_change": 0,
                            "prev_day_close": 0.00999999977648258
                        }
                    ]
                }
            }';
    }
}
