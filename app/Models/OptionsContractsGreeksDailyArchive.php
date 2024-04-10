<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsContractsGreeksDailyArchive extends OptionsContractsGreeks
{
    use HasFactory;

    protected $table = 'options_contracts_greeks_daily_archive';

    protected $fillable = [
        'contract_id',
        'date',
        'delta',
        'gamma',
        'theta',
        'vega',
        'total_delta',
        'total_gamma',
        'open_interest',
        'implied_volatility',
        'volume',
        'archive_date',
        'created_at',
        'updated_at',
    ];
}
