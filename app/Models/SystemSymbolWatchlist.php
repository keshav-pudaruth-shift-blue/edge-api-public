<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemSymbolWatchlist extends BaseModel
{
    use Cachable, HasFactory;

    protected $cacheCooldownSeconds = 86400;

    protected $table = 'system_symbol_watchlist';

    protected $fillable = [
        'symbol',
    ];
}
