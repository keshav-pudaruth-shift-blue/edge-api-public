<?php

namespace App\Repositories;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class HolidayRepository extends BaseRepository
{
    /**
     * HolidayRepository constructor.
     * @param Holiday $model
     */
    public function __construct(protected Holiday $model)
    {
    }

    public function isTodayHoliday(): bool
    {
        return Cache::remember('holiday-exists-'.now()->toDateString(), 3600, function () {
            return $this->getQuery()
                    ->where('public_holiday_date', '=', now()->toDateString())
                    ->count() > 0;
        });
    }

    /**
     * @param Carbon $date
     * @return bool
     */
    public function isDateHoliday(Carbon $date): bool
    {
        return Cache::remember('holiday-exists-'.$date->toDateString(), 3600, function() use ($date) {
            return $this->getQuery()
                ->where('public_holiday_date', '=', $date->toDateString())
                ->count() > 0;
        });
    }
}
