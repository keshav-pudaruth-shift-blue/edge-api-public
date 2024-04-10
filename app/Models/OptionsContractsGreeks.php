<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsContractsGreeks extends BaseModel
{
    use HasFactory;

    protected $table = 'options_contracts_greeks';

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
        'created_at',
        'updated_at',
    ];

    public function contract(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OptionsContracts::class, 'options_contracts_id');
    }
}
