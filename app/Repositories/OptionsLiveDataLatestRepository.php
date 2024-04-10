<?php

namespace App\Repositories;

use App\Models\OptionsLiveDataLatest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OptionsLiveDataLatestRepository extends BaseRepository
{
    public function __construct(protected OptionsLiveDataLatest $model)
    {
    }

    /**
     * @param string $symbol
     * @param Carbon $fromDate
     * @param Carbon $toDate
     * @return Collection
     */
    public function getOptionsDataBySymbolAndDate(string $symbol, Carbon $fromDate, Carbon $toDate): Collection
    {
        return $this->getQuery()
            ->where('symbol', '=', $symbol)
            ->whereDate('expiry_date', '>=', $fromDate)
            ->whereDate('expiry_date', '<=', $toDate)
            ->orderBy('strike')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    public function getAllOptionsDataById(): \Illuminate\Database\Eloquent\Collection|array
    {
        return $this->getQuery()
            ->select('id')
            ->get('id');
    }

    /**
     * @param string $symbol
     * @return Carbon
     */
    public function getEarliestExpiryDate(string $symbol): Carbon
    {
        return $this->getQuery()
            ->where('symbol', '=', $symbol)
            ->orderBy('expiry_date')
            ->first()
            ->expiry_date;
    }
}
