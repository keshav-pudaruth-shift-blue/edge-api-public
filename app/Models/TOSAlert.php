<?php

namespace App\Models;

class TOSAlert extends BaseModel
{
    public $table = 'tos_alerts';

    public $fillable = [
        'name',
        'symbol',
        'expiration_date',
        'strike_price',
        'type'
    ];
}
