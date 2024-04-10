<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OptionsContracts extends BaseModel
{
    public const OPTION_TYPE_CALL = 'C';
    public const OPTION_TYPE_PUT = 'P';

    use HasFactory;

    protected $table = 'options_contracts';

    protected $fillable = [
        'symbol',
        'expiry_date',
        'option_type',
        'strike_price',
        'contract_id'
    ];

    protected $appends = [
        'dte'
    ];

    protected $dates = [
        'expiry_date'
    ];


    public function greeks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OptionsContractsGreeks::class);
    }

    public function relSymbol(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SymbolList::class, 'name', 'symbol');
    }

    public function tradingHours(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            OptionsContractsTradingHours::class,
            OptionsContractsWithTradingHours::class,
            'options_contracts_id',
            'id',
            'id',
            'options_contracts_trading_hours_id'
        );
    }

    protected function dte(): Attribute
    {
        return Attribute::make(function ($value) {
            //Cleanup names for index symbols
            return $this->expiry_date->diffInDays(now());
        });
    }
}
