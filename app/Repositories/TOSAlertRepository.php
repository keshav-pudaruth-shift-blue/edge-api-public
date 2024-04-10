<?php

namespace App\Repositories;

use App\Models\TOSAlert;

class TOSAlertRepository extends BaseRepository
{
    public function __construct(protected TOSAlert $model)
    {
    }
}
