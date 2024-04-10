<?php

namespace App\Models;

class OptionsContractsWithTradingHours extends BaseModel
{
    protected $table = 'options_contracts_with_trading_hours';

    protected $fillable = [
        'options_contracts_id',
        'options_contracts_trading_hours_id'
    ];

    public $timestamps = false;
}
