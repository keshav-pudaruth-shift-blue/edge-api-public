<?php

namespace App\Repositories;

use App\Models\OptionsChainWatchlist;

class OptionsChainWatchlistRepository extends BaseRepository
{
    public function __construct(protected OptionsChainWatchlist $model)
    {
    }

    public function getActive0dte(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()->where('0dte', true)->get();
    }

    public function getActive1dte(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()->where('1dte', true)->get();
    }

    public function getActiveOpex(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()->where('opex', true)->get();
    }

    /**
     * @param string $symbol
     * @return OptionsChainWatchlist
     */
    public function getBySymbol(string $symbol): OptionsChainWatchlist
    {
        return $this->getQuery()->where('symbol', $symbol)->first();
    }
}
