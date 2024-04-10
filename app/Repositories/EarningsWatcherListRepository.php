<?php

namespace App\Repositories;

use App\Models\EarningsWatcherList;

class EarningsWatcherListRepository extends BaseRepository
{
    public function __construct(protected EarningsWatcherList $model) {}

    public function getPendingEarnings(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()
            ->where('date', '>=', now()->toDateString())
            ->where('time', '>=', now()->toTimeString())
            ->get();
    }

    /**
     * @param float $stockPrice
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingEarningsWithValidStockPrice(float $stockPrice): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()
            ->where('date', '>=', now('US/Eastern')->toDateString())
            ->where('time', '>=', now('US/Eastern')->toTimeString())
            ->where('eod_price', '>=', $stockPrice)
            ->orderBy('date')
            ->orderBy('time')
            ->get();
    }

    public function getPendingEarningsRush(float $stockPrice): \Illuminate\Database\Eloquent\Collection
    {
        $nextDte = now('US/Eastern')->addWeekday();
        $holidayRepository = app(HolidayRepository::class);
        if($holidayRepository->isHoliday($nextDte)) {
            $nextDte = now('US/Eastern')->addWeekdays(2);
        }

        return $this->getQuery()
            ->where('date', '>=', now('US/Eastern')->toDateString())
            ->where('time', '>=', now('US/Eastern')->toTimeString())
            ->where('date', '<=', $nextDte->toDateString())
            ->where('time', '<=', '09:30:00') //before market open
            ->where('eod_price', '>=', $stockPrice)
            ->orderBy('date')
            ->orderBy('time')
            ->get();
    }
}
