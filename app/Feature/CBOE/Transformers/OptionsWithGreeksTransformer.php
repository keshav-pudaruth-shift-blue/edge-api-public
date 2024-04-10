<?php

namespace App\Feature\CBOE\Transformers;

use App\Transformers\BaseTransformer;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class OptionsWithGreeksTransformer extends BaseTransformer
{

    /**
     * Transform the resource into an array.
     *
     * @param mixed $resource
     * @return array
     */
    public function transform(array $resource): array
    {
        $optionName = $resource['option'];
        list($symbol, $expiryDate, $type, $strike) = preg_match_all(
            '/([A-Z]+)(\d{6})([CP])(\d{8})/',
            $optionName,
            $matches
        ) ? Arr::flatten(array_slice($matches, 1)) : [];

        $strike = floatval(ltrim((string)$strike, '0'))/1000;
        switch($symbol){
            case 'SPXW':
                $symbol = 'SPX';
                break;
            case 'VIXW':
                $symbol = 'VIX';
                break;
        }

        return [
            'symbol' => $symbol,
            'bearish_volume' => 0,
            'bullish_volume' => 0,
            'expiry_date' => Carbon::createFromFormat('ymd', $expiryDate)->startOfDay(),
            'strike' => $strike,
            'type' => strtolower($type) === 'c' ? 'call' : 'put',
            'current_volume' => $resource['volume'],
            'open_interest' => $resource['open_interest'],
            'implied_volatility' => empty($resource['iv']) ? 0.0 : floatval(
                $resource['iv']
            ),
            'delta' => number_format(abs(floatval($resource['delta'])), 16),
            'gamma' => number_format(abs(floatval($resource['gamma'])), 16),
            'theta' => number_format(abs(floatval($resource['theta'])), 16),
            'vega' => number_format(abs(floatval($resource['vega'])), 16),
            'rho' => number_format(abs(floatval($resource['rho'])), 16),
            'last_updated' => !empty($resource['last_trade_time']) ? Carbon::createFromFormat('Y-m-d\TH:i:s', $resource['last_trade_time'],'America/New_York')->utc() : now()->startOfDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
