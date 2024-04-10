<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;

class Holiday extends BaseModel
{
    use Cachable;

    protected $table = 'holiday';

    protected $fillable = [
        'public_holiday_date'
    ];

    protected $dates = ['public_holiday_date'];
}
