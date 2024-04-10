<?php

namespace App\Feature\UnusualWhales\Transformers;

use App\Transformers\BaseTransformer;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class OptionsWithGreeksTransformer extends BaseTransformer
{
    /**
     * Transform the resource into an array.
     *
     * @param  mixed  $resource
     * @return array
     */
    public function transform(array $resource): array
    {
        return [
            'symbol' => $resource['underlying_symbol'],
            'bearish_volume' => Arr::get($resource, 'bearish_volume', 0),
            'bullish_volume' => Arr::get($resource,'bullish_volume', 0),
            'expiry_date' => Carbon::createFromFormat('Y-m-d',$resource['expires']),
            'strike' => floatval($resource['strike']),
            'type' => $resource['option_type'],
            'current_volume' => $resource['volume'],
            'open_interest' => $resource['open_interest'],
            'implied_volatility' => empty($resource['implied_volatility']) ? 0.0 : floatval($resource['implied_volatility']),
            'delta' => number_format(floatval($resource['delta']), 16),
            'gamma' => number_format(floatval($resource['gamma']),16),
            'last_updated' => Carbon::createFromFormat('Y-m-d\TH:i:s\Z',$resource['last_tape_time']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
