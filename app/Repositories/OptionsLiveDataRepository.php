<?php

namespace App\Repositories;

use App\Models\OptionsLiveData;
use Carbon\Carbon;

class OptionsLiveDataRepository extends BaseRepository
{
    public function __construct(protected OptionsLiveData $model)
    {
    }

    /**
     * @param string $symbol
     * @return object|null
     */
    public function getLastOptionDataBySymbol(string $symbol): object|null
    {
        return $this->getQuery()->where('symbol', $symbol)->orderBy('last_updated', 'desc')->first();
    }

    /**
     * @param Carbon $expiryDate
     * @return mixed
     */
    public function deleteOptionsDataWhereExpiryDate(Carbon $expiryDate): mixed
    {
        return $this->getQuery()
            ->where('expiry_date', '<', $expiryDate->format('Y-m-d'))
            ->delete();
    }

    /**
     * @param array $ids
     * @return mixed
     */
    public function deleteOptionsDataWhereIdNotIn(array $ids): mixed
    {
        return $this->getQuery()
            ->whereNotIn('id', $ids)
            ->delete();
    }
}
