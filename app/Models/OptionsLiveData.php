<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OptionsLiveData extends BaseModel
{
    use HasFactory;

    protected $table = 'options_live_data';

    protected $fillable = [
        'symbol',
        'type',
        'strike',
        'expiry_date',
        'delta',
        'gamma',
        'rho',
        'open_interest',
        'implied_volatility',
        'bearish_volume',
        'bullish_volume',
        'current_volume',
        'last_updated'
    ];

    protected $dates = ['last_updated', 'expiry_date'];

    protected $casts = [
        'expiry_date' => 'datetime:Y-m-d',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function systemSymbolWatchlist(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SystemSymbolWatchlist::class, 'symbol', 'symbol');
    }

}
