<?php

namespace App\Feature\IBOptionsDataSync\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TradingHoursService
{
    public const DATETIME_FORMAT_USA = 'Ymd:Hi';

    /**
     * @param $tradingHourString
     * @param $timezone
     * @return array
     */
    public function parseTradingHourString($tradingHourString, $timezone):array
    {
        $tradingHourStartEnd = explode('-', $tradingHourString);

        Log::debug('Trading hour parsing', [
            'tradingHourStartEnd' => $tradingHourStartEnd,
            'timezone' => $timezone
        ]);

        return [
            'start' => Carbon::createFromFormat(self::DATETIME_FORMAT_USA, $tradingHourStartEnd[0], $timezone)->setTimezone(config('app.timezone')),
            'end' => Carbon::createFromFormat(self::DATETIME_FORMAT_USA, $tradingHourStartEnd[1], $timezone)->setTimezone(config('app.timezone')),
        ];
    }

    //Check if the current time is regular trading hours
    public function isRegularTradingHours(): bool
    {
        return now()->between(now()->hour(13)->minute(30)->seconds(0), now()->hour(20)->minute(0)->seconds(0));
    }
}
