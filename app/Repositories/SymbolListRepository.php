<?php

namespace App\Repositories;

use App\Models\SymbolList;

class SymbolListRepository extends BaseRepository
{
    public function __construct(protected SymbolList $model){}

    /**
     * @param string $symbol
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object
     */
    public function getBySymbol(string $symbol): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null
    {
        return $this->getQuery()->where('name', $symbol)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getEnabledSymbols(): \Illuminate\Database\Eloquent\Collection|array
    {
        return $this->getQuery()
            ->where('is_enabled', '=', true)
            ->get();
    }

    public function queryByMissingContractId()
    {
        return $this->getQuery()
            ->whereNull('ib_contract_id')
            ->where('has_options', '=',true);
    }
}
